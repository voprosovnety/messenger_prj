<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class SearchUsersController
{
    #[Route('/api/users/search', name: 'users_search', methods: ['GET'], priority: 1)]
    public function __invoke(
        Request $request,
        EntityManagerInterface $em,
        UserInterface $me,
    ): JsonResponse {
        /** @var User $me */
        $q = trim($request->query->get('q', ''));
        if ($q === '') {
            return new JsonResponse([]);
        }

        $users = $em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.username LIKE :q OR u.email LIKE :q')
            ->andWhere('u.id != :me')
            ->setParameter('q', '%' . $q . '%')
            ->setParameter('me', $me->getId())
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return new JsonResponse(array_map(fn (User $u) => [
            'username'   => $u->getUsername(),
            'avatar_url' => $u->getAvatarUrl(),
        ], $users));
    }
}
