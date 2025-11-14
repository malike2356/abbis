<?php

declare(strict_types=1);

class GeologyEstimator
{
    private PDO $pdo;
    private float $defaultSearchRadiusKm = 15.0;
    private int $defaultNeighborLimit = 12;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
    }

    /**
     * Predict drilling depth and geology characteristics for a location.
     *
     * @param float $latitude
     * @param float $longitude
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function predict(float $latitude, float $longitude, array $options = []): array
    {
        $radiusKm = (float)($options['radius_km'] ?? $this->defaultSearchRadiusKm);
        $limit = (int)($options['limit'] ?? $this->defaultNeighborLimit);
        $minNeighbors = (int)($options['min_neighbors'] ?? 3);

        $neighbors = $this->findNearestWells($latitude, $longitude, $radiusKm, $limit);

        // Expand search radius if not enough data
        $attempts = 0;
        while (count($neighbors) < $minNeighbors && $attempts < 3) {
            $radiusKm *= 1.5;
            $neighbors = $this->findNearestWells($latitude, $longitude, $radiusKm, $limit);
            $attempts++;
        }

        if (empty($neighbors)) {
            return [
                'success' => false,
                'message' => 'No nearby wells found. Import more geology data to power predictions.',
                'neighbors' => [],
            ];
        }

        $stats = $this->computeStatistics($neighbors);
        $insights = $this->buildInsights($stats, $neighbors);

        return [
            'success' => true,
            'message' => sprintf('Prediction based on %d nearby wells (within %.1f km).', count($neighbors), $stats['max_distance_km']),
            'prediction' => [
                'depth_avg_m' => $stats['depth_avg'],
                'depth_min_m' => $stats['depth_min'],
                'depth_max_m' => $stats['depth_max'],
                'recommended_casing_depth_m' => $stats['recommended_casing_depth'],
                'static_water_level_avg_m' => $stats['static_avg'],
                'yield_avg_m3_per_hr' => $stats['yield_avg'],
                'confidence_score' => $stats['confidence'],
            ],
            'lithology_summary' => $stats['lithology_summary'],
            'aquifer_summary' => $stats['aquifer_summary'],
            'neighbors' => $neighbors,
            'insights' => $insights,
        ];
    }

    /**
     * Store prediction log.
     *
     * @param array<string, mixed> $prediction
     * @return void
     */
    public function logPrediction(array $prediction): void
    {
        $sql = "
            INSERT INTO geology_prediction_logs (
                client_id, user_id, latitude, longitude, region, district,
                predicted_depth_min_m, predicted_depth_avg_m, predicted_depth_max_m,
                confidence_score, neighbor_count, estimation_method, notes
            ) VALUES (
                :client_id, :user_id, :latitude, :longitude, :region, :district,
                :depth_min, :depth_avg, :depth_max,
                :confidence, :neighbor_count, :method, :notes
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':client_id' => $prediction['client_id'] ?? null,
            ':user_id' => $prediction['user_id'] ?? null,
            ':latitude' => $prediction['latitude'],
            ':longitude' => $prediction['longitude'],
            ':region' => $prediction['region'] ?? null,
            ':district' => $prediction['district'] ?? null,
            ':depth_min' => $prediction['depth_min_m'],
            ':depth_avg' => $prediction['depth_avg_m'],
            ':depth_max' => $prediction['depth_max_m'],
            ':confidence' => $prediction['confidence_score'],
            ':neighbor_count' => $prediction['neighbor_count'] ?? 0,
            ':method' => $prediction['estimation_method'] ?? 'inverse_distance_weighting',
            ':notes' => $prediction['notes'] ?? null,
        ]);
    }

    /**
     * Find nearest wells using Haversine distance.
     *
     * @return array<int, array<string, mixed>>
     */
    private function findNearestWells(float $latitude, float $longitude, float $radiusKm, int $limit): array
    {
        $sql = "
            SELECT
                gw.*,
                (
                    6371 * acos(
                        LEAST(
                            GREATEST(
                                cos(radians(:lat)) * cos(radians(gw.latitude)) * cos(radians(gw.longitude) - radians(:lng)) +
                                sin(radians(:lat)) * sin(radians(gw.latitude)),
                                -1
                            ),
                            1
                        )
                    )
                ) AS distance_km
            FROM geology_wells gw
            HAVING distance_km <= :radius
            ORDER BY distance_km ASC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':lat', $latitude);
        $stmt->bindValue(':lng', $longitude);
        $stmt->bindValue(':radius', $radiusKm);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $wells = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($wells as &$well) {
            $well['distance_km'] = isset($well['distance_km']) ? (float)$well['distance_km'] : null;
            $well['depth_m'] = isset($well['depth_m']) ? (float)$well['depth_m'] : null;
            $well['static_water_level_m'] = isset($well['static_water_level_m']) ? (float)$well['static_water_level_m'] : null;
            $well['yield_m3_per_hr'] = isset($well['yield_m3_per_hr']) ? (float)$well['yield_m3_per_hr'] : null;
            $well['tds_mg_per_l'] = isset($well['tds_mg_per_l']) ? (float)$well['tds_mg_per_l'] : null;
            $well['confidence_score'] = isset($well['confidence_score']) ? (float)$well['confidence_score'] : null;
        }

        return $wells;
    }

    /**
     * @param array<int, array<string, mixed>> $neighbors
     * @return array<string, mixed>
     */
    private function computeStatistics(array $neighbors): array
    {
        $sumWeights = 0.0;
        $weightedDepth = 0.0;
        $depths = [];
        $staticLevels = [];
        $yields = [];
        $lithologyTerms = [];
        $aquifers = [];
        $maxDistance = 0.0;

        foreach ($neighbors as $neighbor) {
            $depth = (float)$neighbor['depth_m'];
            $depths[] = $depth;

            $distance = max((float)$neighbor['distance_km'], 0.05); // avoid zero distance
            $weight = 1 / pow($distance, 1.2);
            $sumWeights += $weight;
            $weightedDepth += $depth * $weight;

            if (!empty($neighbor['static_water_level_m'])) {
                $staticLevels[] = (float)$neighbor['static_water_level_m'];
            }
            if (!empty($neighbor['yield_m3_per_hr'])) {
                $yields[] = (float)$neighbor['yield_m3_per_hr'];
            }
            if (!empty($neighbor['lithology'])) {
                $terms = preg_split('/[,;\/]+/', strtolower((string)$neighbor['lithology']));
                foreach ($terms as $term) {
                    $term = trim($term);
                    if ($term !== '') {
                        $lithologyTerms[$term] = ($lithologyTerms[$term] ?? 0) + 1;
                    }
                }
            }
            if (!empty($neighbor['aquifer_type'])) {
                $key = strtolower((string)$neighbor['aquifer_type']);
                $aquifers[$key] = ($aquifers[$key] ?? 0) + 1;
            }

            $maxDistance = max($maxDistance, (float)$neighbor['distance_km']);
        }

        sort($depths);
        $depthCount = count($depths);
        $depthAvg = $depthCount ? array_sum($depths) / $depthCount : null;
        $depthMin = $depthCount ? min($depths) : null;
        $depthMax = $depthCount ? max($depths) : null;

        $depthStd = 0.0;
        if ($depthCount > 1 && $depthAvg !== null) {
            $variance = 0.0;
            foreach ($depths as $depth) {
                $variance += pow($depth - $depthAvg, 2);
            }
            $depthStd = sqrt($variance / $depthCount);
        }

        $weightedDepthAvg = $sumWeights > 0 ? $weightedDepth / $sumWeights : $depthAvg;
        $staticAvg = !empty($staticLevels) ? array_sum($staticLevels) / count($staticLevels) : null;
        $yieldAvg = !empty($yields) ? array_sum($yields) / count($yields) : null;

        arsort($lithologyTerms);
        $lithologySummary = implode(', ', array_slice(array_keys($lithologyTerms), 0, 3));

        arsort($aquifers);
        $aquiferSummary = implode(', ', array_slice(array_keys($aquifers), 0, 3));

        $confidence = min(1.0, max(0.2, (count($neighbors) / 6) * 0.8));
        if ($maxDistance > 30) {
            $confidence *= 0.6;
        } elseif ($maxDistance > 20) {
            $confidence *= 0.75;
        }

        $recommendedCasing = null;
        if ($weightedDepthAvg !== null) {
            $recommendedCasing = round($weightedDepthAvg * 0.75, 2);
        }

        return [
            'depth_avg' => $weightedDepthAvg,
            'depth_min' => $depthMin,
            'depth_max' => $depthMax,
            'depth_std' => $depthStd,
            'static_avg' => $staticAvg,
            'yield_avg' => $yieldAvg,
            'lithology_summary' => $lithologySummary,
            'aquifer_summary' => $aquiferSummary,
            'confidence' => round($confidence, 2),
            'recommended_casing_depth' => $recommendedCasing,
            'max_distance_km' => $maxDistance,
        ];
    }

    /**
     * @param array<string, mixed> $stats
     * @param array<int, array<string, mixed>> $neighbors
     * @return string[]
     */
    private function buildInsights(array $stats, array $neighbors): array
    {
        $insights = [];

        if (!empty($stats['lithology_summary'])) {
            $insights[] = 'Typical lithology: ' . $stats['lithology_summary'];
        }
        if (!empty($stats['aquifer_summary'])) {
            $insights[] = 'Common aquifer types: ' . $stats['aquifer_summary'];
        }

        $avgYield = $stats['yield_avg'];
        if ($avgYield !== null) {
            if ($avgYield >= 10) {
                $insights[] = 'Strong yields expected (≈ ' . number_format($avgYield, 1) . ' m³/hr).';
            } elseif ($avgYield >= 4) {
                $insights[] = 'Moderate yields expected (≈ ' . number_format($avgYield, 1) . ' m³/hr).';
            } else {
                $insights[] = 'Low yields observed nearby; plan for cautious development.';
            }
        }

        $tdsSamples = array_filter(array_column($neighbors, 'tds_mg_per_l'), fn($value) => $value !== null);
        if (!empty($tdsSamples)) {
            $avgTds = array_sum(array_map('floatval', $tdsSamples)) / count($tdsSamples);
            if ($avgTds > 1500) {
                $insights[] = 'High TDS levels reported; consider water treatment.';
            } elseif ($avgTds > 800) {
                $insights[] = 'Moderate TDS levels detected; perform quality tests during drilling.';
            } else {
                $insights[] = 'Water quality generally within acceptable TDS range.';
            }
        }

        return $insights;
    }
}

