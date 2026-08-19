<?php
// src/Dto/DishBatchUpdateDto.php
namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;


class DishReorderInput
{
    /**
     * @var array<array{id: int, sortId: int}>
     */
    #[Groups(['dish:write'])]
    #[Assert\NotBlank]
    #[Assert\Count(min: 1)]
    public array $items = [];
}