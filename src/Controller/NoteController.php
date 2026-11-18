<?php
// src/Controller/NoteController.php

namespace App\Controller;

use App\Entity\Note;
use App\Repository\NoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Entity\User;

#[Route('/api/notes')]
class NoteController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private NoteRepository $notes,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
    ) {}

    #[Route('', name: 'notes_list', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function list(Request $request): JsonResponse
    {
        $entityType = $request->query->get('entityType');
        $entityId = $request->query->get('entityId');

        if (!$entityType || !$entityId || !is_numeric($entityId)) {
            return $this->json(['error' => 'entityType and entityId are required'], 400);
        }

        $allowed = [Note::ENTITY_INQUIRY, Note::ENTITY_EXHIBITOR, Note::ENTITY_PARTNER];
        if (!in_array($entityType, $allowed, true)) {
            return $this->json(['error' => 'Invalid entityType'], 400);
        }

        $items = $this->notes->findForEntity($entityType, (int) $entityId);

        $data = array_map(fn(Note $n) => $this->serializeNote($n), $items);

        return $this->json([
            'member' => $data,
            'totalItems' => count($data),
        ]);
    }

    #[Route('', name: 'notes_create', methods: ['POST'])]
    #[IsGranted('ROLE_STAFF')]
    public function create(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $note = new Note();
        $note->setEntityType($payload['entityType'] ?? '');
        $note->setEntityId((int) ($payload['entityId'] ?? 0));
        $note->setBody($payload['body'] ?? '');
        $note->setAuthor($user);

        $errors = $this->validator->validate($note);
        if (count($errors) > 0) {
            return $this->json([
                'violations' => array_map(fn($e) => [
                    'propertyPath' => $e->getPropertyPath(),
                    'message' => $e->getMessage(),
                ], iterator_to_array($errors)),
            ], 422);
        }

        $this->em->persist($note);
        $this->em->flush();

        return $this->json($this->serializeNote($note), 201);
    }

    #[Route('/{id}', name: 'notes_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_STAFF')]
    public function update(int $id, #[CurrentUser] User $user, Request $request): JsonResponse
    {
        $note = $this->notes->find($id);
        if (!$note) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);
        $isAuthor = $note->getAuthor()?->getId()?->equals($user->getId()) ?? false;

        if (!$isAdmin && !$isAuthor) {
            return $this->json(['error' => 'You can only edit your own notes'], 403);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        if (isset($payload['body'])) {
            $note->setBody($payload['body']);
        }

        $errors = $this->validator->validate($note);
        if (count($errors) > 0) {
            return $this->json([
                'violations' => array_map(fn($e) => [
                    'propertyPath' => $e->getPropertyPath(),
                    'message' => $e->getMessage(),
                ], iterator_to_array($errors)),
            ], 422);
        }

        $this->em->flush();

        return $this->json($this->serializeNote($note));
    }

    #[Route('/{id}', name: 'notes_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): Response
    {
        $note = $this->notes->find($id);
        if (!$note) {
            return $this->json(['error' => 'Not found'], 404);
        }
        $this->em->remove($note);
        $this->em->flush();
        return new Response(null, 204);
    }

    private function serializeNote(Note $note): array
    {
        return [
            'id' => $note->getId(),
            'entityType' => $note->getEntityType(),
            'entityId' => $note->getEntityId(),
            'body' => $note->getBody(),
            'createdOn' => $note->getCreatedOn()?->format(\DateTimeInterface::ATOM),
            'updatedOn' => $note->getUpdatedOn()?->format(\DateTimeInterface::ATOM),
            'author' => [
                'id' => (string) $note->getAuthor()?->getId(),
                'email' => $note->getAuthor()?->getEmail(),
                'givenName' => $note->getAuthor()?->getGivenName(),
                'familyName' => $note->getAuthor()?->getFamilyName(),
            ],
            'canEdit' => $this->canEdit($note),
            'canDelete' => $this->isGranted('ROLE_ADMIN'),
        ];
    }

    private function canEdit(Note $note): bool
    {
        if ($this->isGranted('ROLE_ADMIN')) return true;
        $user = $this->getUser();
        if (!$user instanceof User) return false;
        return $note->getAuthor()?->getId()?->equals($user->getId()) ?? false;
    }
}