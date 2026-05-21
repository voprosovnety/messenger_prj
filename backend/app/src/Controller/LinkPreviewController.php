<?php

namespace App\Controller;

use App\Service\LinkPreviewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/link-preview', name: 'link_preview', methods: ['GET'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class LinkPreviewController extends AbstractController
{
    public function __construct(private readonly LinkPreviewService $linkPreviewService) {}

    public function __invoke(Request $request): Response
    {
        $url = trim($request->query->get('url', ''));
        if (empty($url)) {
            return new JsonResponse(['error' => 'url parameter required'], 400);
        }

        $result = $this->linkPreviewService->fetch($url);
        if ($result === null) {
            return new Response('', 204);
        }

        return new JsonResponse($result);
    }
}
