<?php

namespace App\Service;

use App\Entity\Message;
use App\Entity\MessageReaction;
use Doctrine\ORM\EntityManagerInterface;

class ReactionHelper
{
    public static function buildReactions(EntityManagerInterface $em, Message $msg): array
    {
        $all = $em->getRepository(MessageReaction::class)->findBy(['message' => $msg]);
        return self::groupReactions($all);
    }

    /** @param Message[] $messages */
    public static function buildReactionsForMessages(EntityManagerInterface $em, array $messages): array
    {
        if (!$messages) return [];

        $all = $em->getRepository(MessageReaction::class)->findBy(['message' => $messages]);

        $result = [];
        foreach ($messages as $m) {
            $result[(string) $m->getId()] = [];
        }

        $grouped = [];
        foreach ($all as $r) {
            $mid = (string) $r->getMessage()->getId();
            $e = $r->getEmoji();
            if (!isset($grouped[$mid][$e])) {
                $grouped[$mid][$e] = ['emoji' => $e, 'count' => 0, 'users' => []];
            }
            $grouped[$mid][$e]['count']++;
            $grouped[$mid][$e]['users'][] = $r->getUser()?->getUsername();
        }
        foreach ($grouped as $mid => $emojis) {
            $arr = array_values($emojis);
            usort($arr, fn($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['emoji'], $b['emoji']));
            $result[$mid] = $arr;
        }
        return $result;
    }

    private static function groupReactions(array $reactions): array
    {
        $grouped = [];
        foreach ($reactions as $r) {
            $e = $r->getEmoji();
            if (!isset($grouped[$e])) {
                $grouped[$e] = ['emoji' => $e, 'count' => 0, 'users' => []];
            }
            $grouped[$e]['count']++;
            $grouped[$e]['users'][] = $r->getUser()?->getUsername();
        }
        $arr = array_values($grouped);
        usort($arr, fn($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['emoji'], $b['emoji']));
        return $arr;
    }
}
