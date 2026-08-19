<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use App\Service\UploadFileService;
use App\Service\SettingsService;
use Exception;


final class FilemanagerController extends AbstractController
{
    private $jwtManager;
    private $tokenStorageInterface;
    private $uploadFile;
    private $baseUrl;

    public function __construct(
        TokenStorageInterface $tokenStorageInterface,
        JWTTokenManagerInterface $jwtManager,
        UploadFileService $uploadFile,
        SettingsService $settingsService
    ) {
        $this->jwtManager = $jwtManager;
        $this->tokenStorageInterface = $tokenStorageInterface;
        $this->uploadFile = $uploadFile;

        $env = $_ENV['APP_ENV'] ?? 'dev';
        $base = $settingsService->get('SHARED_FOLDER');
        if ($env == 'dev') {
            $this->baseUrl = $base;
        } else {
            $src = dirname(__DIR__);
            $arr = explode('/', $src);
            $parr = array_pop($arr);
            $arr[] = "public";
            $arr[] = "shared";
            $fpath = implode('/', $arr);
            $this->baseUrl = $fpath;
        }
    }


    #[Route('/api/getFilelist', name: 'app_getfilelist', methods: ['POST'])]
    public function getFilelist(Request $request, EntityManagerInterface $entityManager, Filesystem $filesystem): JsonResponse
    {
        $env = $_ENV['APP_ENV'];
        //-- validate requested path
        $targetFolder = $request->getPayload()->get('targetFolder');
        $levelCount = substr_count($targetFolder, '/');

        if ($levelCount > 1) {
            return $this->json([
                'message' => 'Invalid Path!'
            ]);
        }


        // if ($env == 'dev')
        // {
        //     $baseUrl = '/srv/projects/grande_royal/app/shared';
        // }
        $level2 = [];

        if (!$filesystem->exists($this->baseUrl . '/' . $targetFolder)) {
            $targetFolder = null;
        }


        if ($targetFolder) {
            $targetPath = $this->baseUrl . '/' . $targetFolder;
        }


        //-- scan the root folder "shared"

        $folderList = array_diff(scandir($this->baseUrl), array('..', '.'));
        $l1List = $this->uploadFile->folderContents($folderList, $this->baseUrl);

        $level1 = array(
            'folders' => $l1List['arrFolders'],
            'files' => $l1List['arrFiles']
        );

        // -- if targetFolder scan it, "subfolders"

        if ($targetFolder) {
            $levelCount = substr_count($targetFolder, '/');



            if ($levelCount == 0) {
                $l2List['arrFolders'] = ['parent_count_0'];
                $l2List['arrFiles'] = [];

                if ($filesystem->exists($this->baseUrl . '/' . $targetFolder)) {
                    $subfolderList = array_diff(scandir($targetPath), array('..', '.'));
                    $l2List = $this->uploadFile->folderContents($subfolderList, $targetPath);
                } else {
                    $l2List['arrFolders'] = [];
                    // $parentFolder =  explode('/', $targetFolder)[0];
                    // $subfolderList = array_diff(scandir($parentFolder), array('..', '.'));
                    // $l2List = $this->uploadFile->folderContents($subfolderList, $parentFolder);

                }
                $level2 = array(
                    'folders' => $l2List['arrFolders'],
                    'files' => $l2List['arrFiles']
                );
            } else {

                //-- get folder list of parent since it is the last level anyway no more folders 
                $parentFolder = explode('/', $targetFolder)[0];
                $targetFolderP = $this->baseUrl . '/' . $parentFolder;
                $targetPathP = $targetFolderP;
                $subfolderList = array_diff(scandir($targetPathP), array('..', '.'));

                //-- get file list of requested subfolders 
                $l2ListB = $this->uploadFile->folderContents($subfolderList, $targetPathP);
                $l2List['arrFolders'] = $l2ListB['arrFolders'];

                //-- if requested folder exists "case create new folder"
                $targetFolder = $this->baseUrl . '/' . $targetFolder;
                if ($filesystem->exists($targetFolder)) {
                    $subfolderList = array_diff(scandir($targetFolder), array('..', '.'));
                    $l2ListA = $this->uploadFile->folderContents($subfolderList, $targetPath);
                    $l2List['arrFiles'] = $l2ListA['arrFiles'];
                } else {
                    //-- if requested folder doesn't exists "case delete folder"
                    $l2List['arrFiles'] = $l2ListB['arrFiles'];

                }

                $level2 = array(
                    'folders' => $l2List['arrFolders'],
                    'files' => $l2List['arrFiles']
                );
            }

        }

        $res = array('level1' => $level1, 'level2' => $level2);

        return $this->json([
            'message' => $res
        ]);

    }



    #[Route('/api/createFolder', name: 'app_createFolder', methods: ['POST'])]
    public function createFolder(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $requestedPath = $request->getPayload()->get('requestedPath');
        $newFolderName = $request->getPayload()->get('newFolderName');


        if (!$newFolderName) {
            return $this->json([
                'message' => 'MIssing Folder Name'
            ]);
        }

        $env = $_ENV['APP_ENV'];
        $res = '';

        $filesystem = new Filesystem();
        $basePath = $this->baseUrl . '/' . $requestedPath;

        $fullPath = Path::makeAbsolute($newFolderName, $basePath);

        if ($filesystem->exists($fullPath)) {
            $res = array('status' => 1, 'message' => 'This folder exists!');
            return $this->json([
                'message' => $res
            ]);
        }

        try {
            $filesystem->mkdir($fullPath, 0775);
            $res = array('status' => 0);
        } catch (Exception $e) {
            $res = $e;
        }

        return $this->json([
            'message' => $res
            //'message' => $env
        ]);


    }

    #[Route('/api/deleteFolder', name: 'app_deleteFolder', methods: ['POST'])]
    public function deleteFolder(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $env = $_ENV['APP_ENV'];
        $res = '';

        $filesystem = new Filesystem();

        // if ($env == 'dev')
        // {
        //     $this->baseUrl = '/srv/projects/grande_royal/app/shared';
        // }


        $requestedFolder = $request->getPayload()->get('requestedFolder');

        try {
            $filesystem->remove($this->baseUrl . '/' . $requestedFolder);
            $res = array('status' => 0);
        } catch (Exception $e) {
            $res = $e;
        }

        return $this->json([
            'message' => $res
            //'message' => $env
        ]);
    }

    #[Route('/api/uploadFiles', name: 'app_uploadFiles', methods: ['POST'])]
    public function uploadFiles(
        Request $request,
        EntityManagerInterface $entityManager,
        UploadFileService $uploadFile,
        Filesystem $filesystem
    ): JsonResponse {
        $env = $_ENV['APP_ENV'];
        $res = '';

        // $test = $request->getPayload()->get('test');


        //$uploadedFiles =$request->getPayload()->get('files');
        //$uploadedFile = $request->request->get('file');
        $files = $request->files->get('files');
        $requestedFolder = $request->request->get('folder');
        $arr = [];

        foreach ($files as $file) {
            $arr[] = $uploadFile->index($file, $requestedFolder);
        }


        return $this->json([
            'message' => $arr
        ]);
    }

    #[Route('/api/deleteFile', name: 'app_deleteFile', methods: ['POST'])]
    public function deleteFile(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $filesystem = new Filesystem();
        $env = $_ENV['APP_ENV'];

        $res = '';
        $requestedPath = $request->getPayload()->get('requestedPath');

        $arr = explode('/', $requestedPath);
        unset($arr[1]);
        $fpath = implode('/', $arr);
        $filesystem = new Filesystem();

        $fullPath = $this->baseUrl . $fpath;

        if (!$filesystem->exists($fullPath)) {
            return $this->json([
                'message' => "File doesn't exists!"
            ]);
        }

        if (is_dir($fullPath)) {
            return $this->json([
                'message' => "Not a file!"
            ]);
        }

        try {
            $filesystem->remove($fullPath);
            $res = array('status' => 0);
        } catch (Exception $e) {
            $res = $e;
        }

        return $this->json([
            'message' => $res
        ]);


    }

}
