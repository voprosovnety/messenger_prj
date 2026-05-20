<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\ChatMember;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ToggleSidebarPinController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('/api/chats/{id}/pin-sidebar', name: 'chat_pin_sidebar', methods: ['POST'])]
    public function __invoke(string $id, #[CurrentUser] User $me): JsonResponse
    {
        $chat = $this->em->find(Chat::class, $id);
        if (!$chat) {
            return new JsonResponse(['error' => 'Chat not found'], 404);
        }

        $member = $this->em->getRepository(ChatMember::class)->findOneBy([
            'chat' => $chat,
            'member' => $me,
        ]);

        if (!$member) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $member->setIsPinned(!$member->getIsPinned());
        $this->em->flush();

        return new JsonResponse(['is_pinned' => $member->getIsPinned()]);
    }
}
