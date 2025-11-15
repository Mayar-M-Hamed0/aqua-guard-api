<?php

namespace App\Services;

use App\Models\WaterSample;

class WQICalculatorService
{
    /**
     * Calculate all WQI standards for a water sample
     */
    public function calculateAllWQI(WaterSample $sample): array
    {
        $results = [
            'wqi_who' => $this->calculateWHO($sample),
            'wqi_nsf' => $this->calculateNSF($sample),
            'wqi_ccme' => $this->calculateCCME($sample),
            'wqi_custom' => $this->calculateEgyptianCustom($sample),
        ];

        $results['quality_status'] = $this->determineQualityStatus($results);
        $results['risk_level'] = $this->assessRiskLevel($sample);
        $results['risk_factors'] = $this->identifyRiskFactors($sample);

        return $results;
    }

    /**
     * WHO Standard WQI Calculation - المعادلة الصحيحة
     */
    private function calculateWHO(WaterSample $sample): ?float
    {
        $parameters = [
            'ph' => [
                'value' => $sample->ph,
                'standard' => 7.0,
                'weight' => 0.219, // 21.9%
                'type' => 'ideal'
            ],
            'tds' => [
                'value' => $sample->tds,
                'standard' => 500,
                'weight' => 0.137, // 13.7%
                'type' => 'max'
            ],
            'turbidity' => [
                'value' => $sample->turbidity,
                'standard' => 5,
                'weight' => 0.110, // 11.0%
                'type' => 'max'
            ],
            'dissolved_oxygen' => [
                'value' => $sample->dissolved_oxygen,
                'standard' => 6,
                'weight' => 0.192, // 19.2%
                'type' => 'min'
            ],
            'nitrate' => [
                'value' => $sample->nitrate,
                'standard' => 50,
                'weight' => 0.110, // 11.0%
                'type' => 'max'
            ],
            'fecal_coliform' => [
                'value' => $sample->fecal_coliform,
                'standard' => 0,
                'weight' => 0.232, // 23.2%
                'type' => 'max'
            ],
        ];

        return $this->calculateStandardWQI($parameters);
    }

    /**
     * NSF Water Quality Index - المعادلة الكاملة
     */
    private function calculateNSF(WaterSample $sample): ?float
    {
        $subIndices = [];

        // 1. Dissolved Oxygen (% saturation) - Weight: 0.17
        if ($sample->dissolved_oxygen !== null) {
            $subIndices['do'] = [
                'value' => $this->calculateNSF_DO($sample->dissolved_oxygen),
                'weight' => 0.17
            ];
        }

        // 2. Fecal Coliform (CFU/100ml) - Weight: 0.16
        if ($sample->fecal_coliform !== null) {
            $subIndices['fc'] = [
                'value' => $this->calculateNSF_FC($sample->fecal_coliform),
                'weight' => 0.16
            ];
        }

        // 3. pH - Weight: 0.11
        if ($sample->ph !== null) {
            $subIndices['ph'] = [
                'value' => $this->calculateNSF_pH($sample->ph),
                'weight' => 0.11
            ];
        }

        // 4. BOD (mg/L) - Weight: 0.11
        if ($sample->bod !== null) {
            $subIndices['bod'] = [
                'value' => $this->calculateNSF_BOD($sample->bod),
                'weight' => 0.11
            ];
        }

        // 5. Temperature Change (°C) - Weight: 0.10
        if ($sample->temperature !== null) {
            $subIndices['temp'] = [
                'value' => $this->calculateNSF_Temp($sample->temperature),
                'weight' => 0.10
            ];
        }

        // 6. Total Phosphate (mg/L) - Weight: 0.10
        if ($sample->total_phosphorus !== null) {
            $subIndices['phosphate'] = [
                'value' => $this->calculateNSF_Phosphate($sample->total_phosphorus),
                'weight' => 0.10
            ];
        }

        // 7. Nitrate (mg/L) - Weight: 0.10
        if ($sample->nitrate !== null) {
            $subIndices['nitrate'] = [
                'value' => $this->calculateNSF_Nitrate($sample->nitrate),
                'weight' => 0.10
            ];
        }

        // 8. Turbidity (NTU) - Weight: 0.08
        if ($sample->turbidity !== null) {
            $subIndices['turbidity'] = [
                'value' => $this->calculateNSF_Turbidity($sample->turbidity),
                'weight' => 0.08
            ];
        }

        // 9. TDS (mg/L) - Weight: 0.07
        if ($sample->tds !== null) {
            $subIndices['tds'] = [
                'value' => $this->calculateNSF_TDS($sample->tds),
                'weight' => 0.07
            ];
        }

        if (count($subIndices) < 5) {
            return null;
        }

        $wqi = 0;
        foreach ($subIndices as $index) {
            $wqi += $index['value'] * $index['weight'];
        }

        return round(max(0, min(100, $wqi)), 2);
    }

    /**
     * Canadian Council WQI (CCME) - المعادلة الصحيحة
     */
    private function calculateCCME(WaterSample $sample): ?float
    {
        $parameters = $this->getCCMEParameters($sample);

        if (count($parameters) < 4) {
            return null;
        }

        $failedTests = $this->identifyFailedTests($parameters);

        // F1 = Scope (عدد المتغيرات الفاشلة)
        $F1 = (count($failedTests) / count($parameters)) * 100;

        // F2 = Frequency (عدد القياسات الفاشلة)
        $F2 = (count($failedTests) / count($parameters)) * 100;

        // F3 = Amplitude (مدى تجاوز المعايير)
        $F3 = $this->calculateCCME_Amplitude($failedTests);

        $ccme = 100 - (sqrt($F1 * $F1 + $F2 * $F2 + $F3 * $F3) / 1.732);

        return round(max(0, min(100, $ccme)), 2);
    }

    /**
     * Egyptian Custom WQI - مخصص للمعايير المصرية
     */
    private function calculateEgyptianCustom(WaterSample $sample): ?float
    {
        $parameters = [
            // معاملات صحية حرجة
            'fecal_coliform' => [
                'value' => $sample->fecal_coliform,
                'standard' => 0,
                'weight' => 0.15,
                'type' => 'max'
            ],
            'e_coli' => [
                'value' => $sample->e_coli,
                'standard' => 0,
                'weight' => 0.12,
                'type' => 'max'
            ],
            'nitrate' => [
                'value' => $sample->nitrate,
                'standard' => 45,
                'weight' => 0.10,
                'type' => 'max'
            ],

            // معاملات جودة أساسية
            'ph' => [
                'value' => $sample->ph,
                'standard' => [6.5, 8.5],
                'weight' => 0.10,
                'type' => 'range'
            ],
            'tds' => [
                'value' => $sample->tds,
                'standard' => 1000,
                'weight' => 0.08,
                'type' => 'max'
            ],
            'turbidity' => [
                'value' => $sample->turbidity,
                'standard' => 5,
                'weight' => 0.08,
                'type' => 'max'
            ],
            'dissolved_oxygen' => [
                'value' => $sample->dissolved_oxygen,
                'standard' => 5,
                'weight' => 0.12,
                'type' => 'min'
            ],

            // معادن ثقيلة
            'lead' => [
                'value' => $sample->lead,
                'standard' => 0.01,
                'weight' => 0.10,
                'type' => 'max'
            ],
            'arsenic' => [
                'value' => $sample->arsenic,
                'standard' => 0.01,
                'weight' => 0.08,
                'type' => 'max'
            ],

            // معاملات ثانوية
            'chloride' => [
                'value' => $sample->chloride,
                'standard' => 250,
                'weight' => 0.04,
                'type' => 'max'
            ],
            'sulfate' => [
                'value' => $sample->sulfate,
                'standard' => 250,
                'weight' => 0.03,
                'type' => 'max'
            ],
        ];

        return $this->calculateStandardWQI($parameters);
    }

    /**
     * Determine overall quality status
     */
    private function determineQualityStatus(array $results): string
    {
        $wqis = array_filter([
            $results['wqi_who'],
            $results['wqi_nsf'],
            $results['wqi_ccme'],
            $results['wqi_custom']
        ], function($wqi) {
            return $wqi !== null && $wqi >= 0;
        });

        if (empty($wqis)) {
            return 'unknown';
        }

        $avgWqi = array_sum($wqis) / count($wqis);

        if ($avgWqi >= 90) return 'excellent';
        if ($avgWqi >= 70) return 'good';
        if ($avgWqi >= 50) return 'fair';
        if ($avgWqi >= 25) return 'poor';
        return 'very_poor';
    }

    /**
     * Assess risk level based on critical parameters
     */
    private function assessRiskLevel(WaterSample $sample): string
    {
        $criticalViolations = 0;
        $majorViolations = 0;

        // Check critical parameters
        if (($sample->fecal_coliform ?? 0) > 0 || ($sample->e_coli ?? 0) > 0) {
            $criticalViolations++;
        }

        if (($sample->lead ?? 0) > 0.01 || ($sample->arsenic ?? 0) > 0.01 || ($sample->mercury ?? 0) > 0.001) {
            $criticalViolations++;
        }

        if (($sample->nitrate ?? 0) > 50) {
            $majorViolations++;
        }

        if (($sample->ph ?? 7) < 6.5 || ($sample->ph ?? 7) > 8.5) {
            $majorViolations++;
        }

        if (($sample->dissolved_oxygen ?? 8) < 4) {
            $majorViolations++;
        }

        if (($sample->turbidity ?? 0) > 10) {
            $majorViolations++;
        }

        if ($criticalViolations >= 2) return 'critical';
        if ($criticalViolations >= 1) return 'high';
        if ($majorViolations >= 3) return 'high';
        if ($majorViolations >= 2) return 'medium';
        if ($majorViolations >= 1) return 'low';

        return 'very_low';
    }

    /**
     * Identify specific risk factors
     */
    private function identifyRiskFactors(WaterSample $sample): array
    {
        $risks = [];

        // Critical risks
        if (($sample->fecal_coliform ?? 0) > 0) {
            $risks[] = [
                'parameter' => 'fecal_coliform',
                'severity' => 'critical',
                'message' => 'Bacterial contamination detected - unsafe for drinking',
                'value' => $sample->fecal_coliform,
                'standard' => 0
            ];
        }

        if (($sample->e_coli ?? 0) > 0) {
            $risks[] = [
                'parameter' => 'e_coli',
                'severity' => 'critical',
                'message' => 'E. coli detected - indicates fecal contamination',
                'value' => $sample->e_coli,
                'standard' => 0
            ];
        }

        if (($sample->lead ?? 0) > 0.01) {
            $risks[] = [
                'parameter' => 'lead',
                'severity' => 'critical',
                'message' => 'Lead exceeds safe limits - risk of neurological damage',
                'value' => $sample->lead,
                'standard' => 0.01
            ];
        }

        // High risks
        if (($sample->nitrate ?? 0) > 50) {
            $risks[] = [
                'parameter' => 'nitrate',
                'severity' => 'high',
                'message' => 'High nitrate levels - risk for infants (blue baby syndrome)',
                'value' => $sample->nitrate,
                'standard' => 50
            ];
        }

        if (($sample->arsenic ?? 0) > 0.01) {
            $risks[] = [
                'parameter' => 'arsenic',
                'severity' => 'high',
                'message' => 'Arsenic exceeds safe limits - carcinogenic risk',
                'value' => $sample->arsenic,
                'standard' => 0.01
            ];
        }

        // Medium risks
        if (($sample->dissolved_oxygen ?? 8) < 4) {
            $risks[] = [
                'parameter' => 'dissolved_oxygen',
                'severity' => 'medium',
                'message' => 'Low dissolved oxygen - indicates organic pollution',
                'value' => $sample->dissolved_oxygen,
                'standard' => 4
            ];
        }

        if (($sample->turbidity ?? 0) > 5) {
            $risks[] = [
                'parameter' => 'turbidity',
                'severity' => 'medium',
                'message' => 'High turbidity - may harbor pathogens and affect disinfection',
                'value' => $sample->turbidity,
                'standard' => 5
            ];
        }

        if (($sample->ph ?? 7) < 6.5 || ($sample->ph ?? 7) > 8.5) {
            $risks[] = [
                'parameter' => 'ph',
                'severity' => 'medium',
                'message' => 'pH outside optimal range - may affect treatment and corrosion',
                'value' => $sample->ph,
                'standard' => '6.5-8.5'
            ];
        }

        // Low risks
        if (($sample->tds ?? 0) > 1000) {
            $risks[] = [
                'parameter' => 'tds',
                'severity' => 'low',
                'message' => 'High total dissolved solids - may affect taste and usability',
                'value' => $sample->tds,
                'standard' => 1000
            ];
        }

        return $risks;
    }

    // ========== معادلات NSF الكاملة ==========

    private function calculateNSF_DO($do): float
    {
        // منحنى NSF الحقيقي للأكسجين الذائب (mg/L)
        if ($do >= 10) return 100;
        if ($do >= 9) return 95;
        if ($do >= 8) return 90;
        if ($do >= 7) return 85;
        if ($do >= 6) return 80;
        if ($do >= 5) return 70;
        if ($do >= 4) return 60;
        if ($do >= 3) return 50;
        if ($do >= 2) return 30;
        if ($do >= 1) return 20;
        return 10;
    }

    private function calculateNSF_FC($fc): float
    {
        // منحنى NSF الحقيقي للقولونيات البرازية (CFU/100ml)
        if ($fc <= 1) return 98;
        if ($fc <= 5) return 92;
        if ($fc <= 10) return 85;
        if ($fc <= 20) return 78;
        if ($fc <= 50) return 65;
        if ($fc <= 100) return 55;
        if ($fc <= 200) return 43;
        if ($fc <= 500) return 30;
        if ($fc <= 1000) return 20;
        return 10;
    }

    private function calculateNSF_pH($ph): float
    {
        // منحنى NSF الحقيقي لدرجة الحموضة
        if ($ph >= 7.0 && $ph <= 7.5) return 92;
        if ($ph >= 6.5 && $ph <= 8.0) return 85;
        if ($ph >= 6.0 && $ph <= 8.5) return 75;
        if ($ph >= 5.5 && $ph <= 9.0) return 60;
        if ($ph >= 5.0 && $ph <= 9.5) return 45;
        return 25;
    }

    private function calculateNSF_BOD($bod): float
    {
        // منحنى NSF الحقيقي للطلب الأوكسجيني الحيوي (mg/L)
        if ($bod <= 1) return 98;
        if ($bod <= 2) return 90;
        if ($bod <= 3) return 80;
        if ($bod <= 4) return 70;
        if ($bod <= 5) return 60;
        if ($bod <= 6) return 50;
        if ($bod <= 8) return 40;
        if ($bod <= 10) return 30;
        if ($bod <= 15) return 20;
        return 10;
    }

    private function calculateNSF_Temp($temp): float
    {
        // درجة الحرارة - تعتمد على الانحراف عن درجة الحرارة الطبيعية (20-25°C)
        $deviation = abs($temp - 22.5); // الانحراف عن المتوسط
        if ($deviation <= 2) return 90;
        if ($deviation <= 5) return 75;
        if ($deviation <= 10) return 50;
        if ($deviation <= 15) return 30;
        return 15;
    }

    private function calculateNSF_Phosphate($phosphate): float
    {
        // منحنى NSF الحقيقي للفوسفات (mg/L)
        if ($phosphate <= 0.02) return 97;
        if ($phosphate <= 0.05) return 90;
        if ($phosphate <= 0.1) return 80;
        if ($phosphate <= 0.2) return 70;
        if ($phosphate <= 0.5) return 55;
        if ($phosphate <= 1.0) return 40;
        if ($phosphate <= 2.0) return 25;
        return 15;
    }

    private function calculateNSF_Nitrate($nitrate): float
    {
        // منحنى NSF الحقيقي للنترات (mg/L)
        if ($nitrate <= 1) return 98;
        if ($nitrate <= 2) return 95;
        if ($nitrate <= 5) return 90;
        if ($nitrate <= 10) return 80;
        if ($nitrate <= 20) return 65;
        if ($nitrate <= 30) return 50;
        if ($nitrate <= 40) return 35;
        if ($nitrate <= 50) return 25;
        return 15;
    }

    private function calculateNSF_Turbidity($turbidity): float
    {
        // منحنى NSF الحقيقي للعكارة (NTU)
        if ($turbidity <= 0.5) return 98;
        if ($turbidity <= 1) return 90;
        if ($turbidity <= 2) return 80;
        if ($turbidity <= 5) return 65;
        if ($turbidity <= 10) return 50;
        if ($turbidity <= 20) return 35;
        if ($turbidity <= 50) return 25;
        return 15;
    }

    private function calculateNSF_TDS($tds): float
    {
        // منحنى NSF الحقيقي للمواد الصلبة الذائبة (mg/L)
        if ($tds <= 100) return 95;
        if ($tds <= 200) return 90;
        if ($tds <= 300) return 85;
        if ($tds <= 400) return 80;
        if ($tds <= 500) return 75;
        if ($tds <= 600) return 70;
        if ($tds <= 700) return 65;
        if ($tds <= 800) return 60;
        if ($tds <= 900) return 55;
        if ($tds <= 1000) return 50;
        if ($tds <= 1500) return 35;
        return 20;
    }

    // ========== دوال مساعدة محسنة ==========

    private function calculateStandardWQI(array $parameters): ?float
    {
        $totalWeight = 0;
        $weightedSum = 0;
        $validParams = 0;

        foreach ($parameters as $param) {
            if ($param['value'] === null) continue;

            $qi = $this->calculateQualityIndex(
                $param['value'],
                $param['standard'],
                $param['type']
            );

            $weightedSum += $qi * $param['weight'];
            $totalWeight += $param['weight'];
            $validParams++;
        }

        if ($validParams < 3 || $totalWeight <= 0) {
            return null;
        }

        $wqi = $weightedSum / $totalWeight;
        return round(max(0, min(100, $wqi)), 2);
    }

    private function calculateQualityIndex($value, $standard, $type): float
    {
        switch ($type) {
            case 'max':
                if ($standard == 0) {
                    return $value == 0 ? 100 : 0;
                }
                if ($value <= $standard) return 100;
                $exceedance = (($value - $standard) / $standard) * 100;
                return max(0, 100 - min(100, $exceedance));

            case 'min':
                if ($value >= $standard) return 100;
                $ratio = ($value / $standard) * 100;
                return max(0, min(100, $ratio));

            case 'ideal':
                $deviation = abs($value - $standard);
                if ($deviation == 0) return 100;
                $penalty = min(100, $deviation * 20); // 5% penalty per 0.1 deviation
                return max(0, 100 - $penalty);

            case 'range':
                list($min, $max) = $standard;
                if ($value >= $min && $value <= $max) return 100;
                if ($value < $min) {
                    $deviation = ($min - $value) / $min;
                    return max(0, 100 - ($deviation * 100));
                }
                if ($value > $max) {
                    $deviation = ($value - $max) / $max;
                    return max(0, 100 - ($deviation * 100));
                }
                return 50;

            default:
                return 50;
        }
    }

    private function getCCMEParameters(WaterSample $sample): array
    {
        return array_filter([
            'ph' => [
                'value' => $sample->ph,
                'min' => 6.5,
                'max' => 8.5
            ],
            'tds' => [
                'value' => $sample->tds,
                'max' => 500
            ],
            'turbidity' => [
                'value' => $sample->turbidity,
                'max' => 5
            ],
            'dissolved_oxygen' => [
                'value' => $sample->dissolved_oxygen,
                'min' => 4
            ],
            'nitrate' => [
                'value' => $sample->nitrate,
                'max' => 50
            ],
            'fecal_coliform' => [
                'value' => $sample->fecal_coliform,
                'max' => 0
            ],
            'bod' => [
                'value' => $sample->bod,
                'max' => 5
            ],
            'total_phosphorus' => [
                'value' => $sample->total_phosphorus,
                'max' => 0.1
            ],
        ], function($p) {
            return $p['value'] !== null;
        });
    }

    private function identifyFailedTests(array $parameters): array
    {
        $failed = [];
        foreach ($parameters as $name => $param) {
            if (isset($param['max']) && $param['value'] > $param['max']) {
                $failed[$name] = $param;
            }
            if (isset($param['min']) && $param['value'] < $param['min']) {
                $failed[$name] = $param;
            }
        }
        return $failed;
    }

    private function calculateCCME_Amplitude(array $failedTests): float
    {
        if (empty($failedTests)) return 0;

        $excursions = [];
        foreach ($failedTests as $test) {
            if (isset($test['max']) && $test['max'] > 0) {
                $excursion = ($test['value'] / $test['max']) - 1;
                $excursions[] = $excursion;
            } elseif (isset($test['min']) && $test['value'] > 0) {
                $excursion = ($test['min'] / $test['value']) - 1;
                $excursions[] = $excursion;
            }
        }

        if (empty($excursions)) return 0;

        $nse = array_sum($excursions) / count($excursions);
        return ($nse / 0.01) * 100;
    }
}
