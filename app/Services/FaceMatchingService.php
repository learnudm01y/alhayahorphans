<?php

namespace App\Services;

class FaceMatchingService
{
    public function __construct(
        private ?float $autoThreshold = null,
        private ?float $reviewThreshold = null,
    ) {
        $this->autoThreshold ??= (float) config('face_matching.auto_threshold', 0.32);
        $this->reviewThreshold ??= (float) config('face_matching.review_threshold', 0.45);
    }

    public function analyze(iterable $attachments): array
    {
        $grouped = [];
        $stats = [
            'input_images' => 0,
            'usable_embeddings' => 0,
            'skipped_embeddings' => 0,
            'comparison_groups' => 0,
            'single_image_groups' => 0,
            'auto_pairs' => 0,
            'review_pairs' => 0,
            'different_pairs' => 0,
            'auto_duplicate_groups' => 0,
            'deletion_candidates' => 0,
        ];

        foreach ($attachments as $attachment) {
            $stats['input_images']++;
            $record = is_array($attachment) ? $attachment : (array) $attachment;
            $key = (string) ($record['person_identity_number'] ?? '')
                . '|' . (string) ($record['file_type'] ?? '');
            $grouped[$key][] = $record;
        }

        $groups = [];
        $pairs = [];

        foreach ($grouped as $records) {
            $records = array_values(array_filter($records, function (array $record): bool {
                return isset($record['id']) && $this->isValidEmbedding($record['embedding'] ?? null);
            }));

            $stats['usable_embeddings'] += count($records);

            if (count($records) < 2) {
                $stats['single_image_groups']++;
                continue;
            }

            $stats['comparison_groups']++;
            usort($records, fn (array $a, array $b): int => $this->newestFirst($a, $b));

            $pairMap = [];
            $autoEdges = [];

            for ($i = 0, $count = count($records); $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $records[$i];
                    $b = $records[$j];
                    $distance = $this->distance($a['embedding'], $b['embedding']);
                    $classification = $this->classify($distance);
                    $pair = [
                        'person_identity_number' => (string) ($a['person_identity_number'] ?? ''),
                        'file_type' => (string) ($a['file_type'] ?? ''),
                        'a_id' => (int) $a['id'],
                        'b_id' => (int) $b['id'],
                        'distance' => round($distance, 6),
                        'display_similarity' => round(max(0, 1 - $distance), 4),
                        'classification' => $classification,
                    ];

                    $pairs[] = $pair;
                    $pairMap[$this->pairKey((int) $a['id'], (int) $b['id'])] = $pair;
                    $stats[$classification . '_pairs']++;

                    if ($classification === 'auto') {
                        $autoEdges[] = $pair;
                    }
                }
            }

            usort($autoEdges, function (array $a, array $b): int {
                return [$a['distance'], $a['a_id'], $a['b_id']]
                    <=> [$b['distance'], $b['a_id'], $b['b_id']];
            });

            $clusters = [];
            $clusterOf = [];
            foreach ($records as $index => $record) {
                $clusters[$index] = [(int) $record['id']];
                $clusterOf[(int) $record['id']] = $index;
            }

            foreach ($autoEdges as $edge) {
                $left = $clusterOf[$edge['a_id']];
                $right = $clusterOf[$edge['b_id']];

                if ($left === $right || !$this->canMerge($clusters[$left], $clusters[$right], $pairMap)) {
                    continue;
                }

                $merged = array_merge($clusters[$left], $clusters[$right]);
                $clusters[$left] = $merged;
                unset($clusters[$right]);

                foreach ($merged as $id) {
                    $clusterOf[$id] = $left;
                }
            }

            $byId = [];
            foreach ($records as $record) {
                $byId[(int) $record['id']] = $record;
            }

            foreach ($clusters as $ids) {
                if (count($ids) < 2) {
                    continue;
                }

                usort($ids, fn (int $a, int $b): int => $this->newestFirst($byId[$a], $byId[$b]));
                $keepId = array_shift($ids);
                $maxDistance = 0.0;

                foreach ($ids as $id) {
                    $maxDistance = max($maxDistance, $pairMap[$this->pairKey($keepId, $id)]['distance']);
                }

                $groups[] = [
                    'person_identity_number' => (string) ($records[0]['person_identity_number'] ?? ''),
                    'file_type' => (string) ($records[0]['file_type'] ?? ''),
                    'keep_id' => $keepId,
                    'delete_ids' => array_values($ids),
                    'attachment_ids' => array_values(array_merge([$keepId], $ids)),
                    'max_distance_from_keep' => round($maxDistance, 6),
                ];
                $stats['auto_duplicate_groups']++;
                $stats['deletion_candidates'] += count($ids);
            }
        }

        $stats['skipped_embeddings'] = $stats['input_images'] - $stats['usable_embeddings'];

        return ['groups' => $groups, 'pairs' => $pairs, 'stats' => $stats];
    }

    public function distance(array $a, array $b): float
    {
        if (!$this->isValidEmbedding($a) || !$this->isValidEmbedding($b)) {
            throw new \InvalidArgumentException('Face embeddings must contain 128 finite numeric values.');
        }

        $sum = 0.0;
        for ($i = 0; $i < 128; $i++) {
            $difference = (float) $a[$i] - (float) $b[$i];
            $sum += $difference * $difference;
        }

        return sqrt($sum);
    }

    private function classify(float $distance): string
    {
        if ($distance <= $this->autoThreshold) {
            return 'auto';
        }

        if ($distance <= $this->reviewThreshold) {
            return 'review';
        }

        return 'different';
    }

    private function canMerge(array $left, array $right, array $pairMap): bool
    {
        foreach ($left as $a) {
            foreach ($right as $b) {
                $pair = $pairMap[$this->pairKey($a, $b)] ?? null;
                if (!$pair || $pair['classification'] !== 'auto') {
                    return false;
                }
            }
        }

        return true;
    }

    private function isValidEmbedding(mixed $embedding): bool
    {
        if (!is_array($embedding) || count($embedding) !== 128) {
            return false;
        }

        foreach ($embedding as $value) {
            if (!is_numeric($value) || !is_finite((float) $value)) {
                return false;
            }
        }

        return true;
    }

    private function newestFirst(array $a, array $b): int
    {
        $byDate = strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));

        return $byDate !== 0 ? $byDate : ((int) $b['id'] <=> (int) $a['id']);
    }

    private function pairKey(int $a, int $b): string
    {
        return min($a, $b) . ':' . max($a, $b);
    }
}
