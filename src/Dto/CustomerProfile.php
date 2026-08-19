<?php
// src/Dto/CustomerProfile.php
namespace App\Dto;

use Symfony\Component\Serializer\Annotation\Groups;

class CustomerProfile
{
    #[Groups(['customer:profile'])]
    public int $id;
    
    #[Groups(['customer:profile'])]
    public string $name;
    
    #[Groups(['customer:profile'])]
    public string $email;
    
    #[Groups(['customer:profile'])]
    public ?string $telephone;

    #[Groups(['customer:profile'])]
    public ?string $address;
    
    #[Groups(['customer:profile'])]
    public int $visitCount;
    
    #[Groups(['customer:profile'])]
    public array $activeRewards = [];
    
    #[Groups(['customer:profile'])]
    public array $rewardHistory = [];
    
    // ===== NEW: ALL milestones with status =====
    #[Groups(['customer:profile'])]
    public array $allMilestones = [];
}