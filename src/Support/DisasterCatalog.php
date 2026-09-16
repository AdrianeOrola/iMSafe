<?php
declare(strict_types=1);

namespace ImSafe\Support;

use RuntimeException;

final class DisasterCatalog
{
    public static function friendlyGroup(string $group): string
    {
        return match ($group) {
            'Hydrometeorological' => 'Weather and water disasters',
            'Geological' => 'Earth and ground disasters',
            'Fire & Human-Caused' => 'Fire and dangerous material incidents',
            default => $group,
        };
    }

    public static function friendlyDisaster(string $disaster): string
    {
        return match ($disaster) {
            'Tropical Cyclone' => 'Typhoon',
            'Volcanic Eruption' => 'Volcanic eruption',
            'Hazardous Material Incident' => 'Chemical or gas incident',
            default => $disaster,
        };
    }

    public static function friendlyEffect(string $effect): string
    {
        return match ($effect) {
            'Urban or street flooding' => 'Street flooding',
            'Dam or levee-related flooding' => 'Dam or dike failure',
            'Rain-induced flooding' => 'Flooding caused by rain',
            'Rain-induced landslide' => 'Rain-caused landslide',
            'Mudflow or debris flow' => 'Mud or debris flow',
            'Earthquake-induced landslide' => 'Earthquake-caused landslide',
            'Severe wind gusts' => 'Strong wind gusts',
            'Intense rainfall' => 'Very heavy rain',
            'Ground rupture' => 'Ground crack',
            'Aftershock impacts' => 'Aftershock damage',
            'Pyroclastic flow' => 'Fast-moving hot ash or gas',
            'Lahar' => 'Lahar or volcanic mudflow',
            'Coastal inundation' => 'Seawater flooding inland',
            'Rapid sea-level change' => 'Sudden sea-level change',
            'Residential fire' => 'House fire',
            'Commercial fire' => 'Building or business fire',
            'Industrial fire' => 'Factory fire',
            'Unknown hazardous substance' => 'Unknown dangerous substance',
            default => $effect,
        };
    }

    /** @return array<string,array<string,mixed>> */
    public static function disasters(): array
    {
        $floodLevels = [];
        foreach (FloodLevels::options() as $level => $measurement) {
            $floodLevels[] = ['value' => $level, 'label' => $level . ' - ' . $measurement];
        }

        $roadOptions = ['Passable', 'Passable with caution', 'Not passable', 'Unknown'];
        $powerOptions = ['Power available', 'Intermittent power', 'Power outage', 'Unknown'];

        $catalog = [
            'Flood' => [
                'group' => 'Weather and water disasters',
                'effects' => ['Flash flood', 'River flooding', 'Coastal flooding', 'Street flooding', 'Dam or dike failure'],
                'questions' => [
                    ['key' => 'water_level', 'label' => 'Estimated water depth', 'type' => 'select', 'required' => true, 'options' => $floodLevels, 'help' => 'Choose the nearest visible depth.'],
                    ['key' => 'water_trend', 'label' => 'Water level now', 'type' => 'select', 'required' => true, 'options' => ['Rising', 'Stable', 'Receding']],
                    ['key' => 'road_passability', 'label' => 'Can vehicles use the road?', 'type' => 'select', 'required' => true, 'options' => $roadOptions],
                    ['key' => 'people_stranded', 'label' => 'People stranded', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 999999, 'step' => 1, 'default' => '0'],
                    ['key' => 'houses_affected', 'label' => 'Houses affected', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 999999, 'step' => 1, 'default' => '0'],
                    ['key' => 'evacuation_needed', 'label' => 'Evacuation is needed', 'type' => 'checkbox', 'required' => false],
                    ['key' => 'rescue_needed', 'label' => 'Rescue is needed', 'type' => 'checkbox', 'required' => false],
                ],
            ],
            'Typhoon' => [
                'group' => 'Weather and water disasters',
                'effects' => ['Strong winds', 'Storm surge', 'Heavy rain', 'Flooding caused by rain'],
                'questions' => [
                    ['key' => 'wind_condition', 'label' => 'Observed wind condition', 'type' => 'select', 'required' => true, 'options' => ['Light winds', 'Strong winds moving large branches', 'Very strong winds damaging roofs', 'Too dangerous to observe']],
                    ['key' => 'rain_condition', 'label' => 'Rain condition', 'type' => 'select', 'required' => true, 'options' => ['No rain', 'Light rain', 'Heavy rain', 'Very heavy rain']],
                    ['key' => 'power_status', 'label' => 'Electricity status', 'type' => 'select', 'required' => true, 'options' => $powerOptions],
                    ['key' => 'road_passability', 'label' => 'Can vehicles use the road?', 'type' => 'select', 'required' => true, 'options' => $roadOptions],
                    ['key' => 'homes_damaged', 'label' => 'Homes visibly damaged', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 999999, 'step' => 1, 'default' => '0'],
                    ['key' => 'evacuation_needed', 'label' => 'Evacuation is needed', 'type' => 'checkbox', 'required' => false],
                ],
            ],
            'Thunderstorm' => [
                'group' => 'Weather and water disasters',
                'effects' => ['Lightning', 'Strong wind gusts', 'Hail', 'Very heavy rain'],
                'questions' => [
                    ['key' => 'storm_condition', 'label' => 'Storm condition now', 'type' => 'select', 'required' => true, 'options' => ['Getting stronger', 'Steady', 'Weakening', 'Already passed']],
                    ['key' => 'visibility', 'label' => 'Visibility', 'type' => 'select', 'required' => true, 'options' => ['Clear', 'Reduced', 'Very poor']],
                    ['key' => 'local_flooding', 'label' => 'Flooding is present', 'type' => 'select', 'required' => true, 'options' => ['No', 'Yes', 'Unknown']],
                    ['key' => 'power_status', 'label' => 'Electricity status', 'type' => 'select', 'required' => true, 'options' => $powerOptions],
                    ['key' => 'road_passability', 'label' => 'Can vehicles use the road?', 'type' => 'select', 'required' => true, 'options' => $roadOptions],
                ],
            ],
            'Earthquake' => [
                'group' => 'Earth and ground disasters',
                'effects' => ['Ground shaking', 'Building damage or collapse', 'Ground crack', 'Liquefaction', 'Aftershock damage'],
                'questions' => [
                    ['key' => 'earthquake_magnitude', 'label' => 'Reported magnitude, if officially known', 'type' => 'number', 'required' => false, 'min' => 0, 'max' => 10, 'step' => 0.1, 'placeholder' => 'Leave blank if unknown', 'help' => 'Use an official value only. Do not estimate.'],
                    ['key' => 'shaking_strength', 'label' => 'How strong did the shaking feel?', 'type' => 'select', 'required' => true, 'options' => ['Weak', 'Light', 'Moderate', 'Strong', 'Very strong', 'Severe']],
                    ['key' => 'building_damage', 'label' => 'Visible building damage', 'type' => 'select', 'required' => true, 'options' => ['No visible damage', 'Minor cracks or fallen objects', 'Major structural damage', 'One or more buildings collapsed']],
                    ['key' => 'people_trapped', 'label' => 'People trapped', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 999999, 'step' => 1, 'default' => '0'],
                    ['key' => 'road_bridge_status', 'label' => 'Road and bridge condition', 'type' => 'select', 'required' => true, 'options' => ['Passable', 'Use with caution', 'Blocked or damaged', 'Unknown']],
                    ['key' => 'aftershocks_felt', 'label' => 'Aftershocks have been felt', 'type' => 'checkbox', 'required' => false],
                    ['key' => 'rescue_needed', 'label' => 'Rescue is needed', 'type' => 'checkbox', 'required' => false],
                ],
            ],
            'Landslide' => [
                'group' => 'Earth and ground disasters',
                'effects' => ['Rain-caused landslide', 'Rockfall', 'Mud or debris flow', 'Slope collapse', 'Earthquake-caused landslide'],
                'questions' => [
                    ['key' => 'landslide_movement', 'label' => 'Ground movement now', 'type' => 'select', 'required' => true, 'options' => ['Still moving', 'Movement has stopped', 'Unknown']],
                    ['key' => 'road_passability', 'label' => 'Can vehicles use the road?', 'type' => 'select', 'required' => true, 'options' => $roadOptions],
                    ['key' => 'homes_at_risk', 'label' => 'Homes damaged or threatened', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 999999, 'step' => 1, 'default' => '0'],
                    ['key' => 'people_trapped', 'label' => 'People trapped', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 999999, 'step' => 1, 'default' => '0'],
                    ['key' => 'new_ground_cracks', 'label' => 'New ground cracks are visible', 'type' => 'checkbox', 'required' => false],
                    ['key' => 'evacuation_needed', 'label' => 'Evacuation is needed', 'type' => 'checkbox', 'required' => false],
                ],
            ],
            'Volcanic eruption' => [
                'group' => 'Earth and ground disasters',
                'effects' => ['Ashfall', 'Lava flow', 'Fast-moving hot ash or gas', 'Lahar or volcanic mudflow', 'Volcanic gas'],
                'questions' => [
                    ['key' => 'ashfall_level', 'label' => 'Observed ashfall', 'type' => 'select', 'required' => true, 'options' => ['No ashfall', 'Light dusting', 'Moderate ash buildup', 'Heavy ash buildup']],
                    ['key' => 'visibility', 'label' => 'Visibility', 'type' => 'select', 'required' => true, 'options' => ['Clear', 'Reduced', 'Very poor']],
                    ['key' => 'breathing_difficulty', 'label' => 'People with breathing difficulty', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 999999, 'step' => 1, 'default' => '0'],
                    ['key' => 'evacuation_order', 'label' => 'Evacuation instruction heard', 'type' => 'select', 'required' => true, 'options' => ['None heard', 'Residents advised to prepare', 'Residents ordered to evacuate', 'Unknown']],
                    ['key' => 'road_passability', 'label' => 'Can vehicles use the road?', 'type' => 'select', 'required' => true, 'options' => $roadOptions],
                    ['key' => 'lahar_observed', 'label' => 'Mudflow or lahar is visible', 'type' => 'checkbox', 'required' => false],
                ],
            ],
            'Tsunami' => [
                'group' => 'Earth and ground disasters',
                'effects' => ['Seawater flooding inland', 'Sudden sea-level change', 'Strong coastal currents', 'Wave damage'],
                'questions' => [
                    ['key' => 'wave_status', 'label' => 'Observed sea condition', 'type' => 'select', 'required' => true, 'options' => ['Sea suddenly receding', 'Sea level rising unusually', 'Large waves arriving', 'Waves have passed', 'Unknown']],
                    ['key' => 'coastal_water_depth', 'label' => 'Estimated water depth inland', 'type' => 'select', 'required' => true, 'options' => $floodLevels],
                    ['key' => 'inland_reach_meters', 'label' => 'Approximate distance water reached inland, in meters', 'type' => 'number', 'required' => false, 'min' => 0, 'max' => 100000, 'step' => 1, 'placeholder' => 'Leave blank if unknown'],
                    ['key' => 'road_passability', 'label' => 'Can vehicles use the coastal road?', 'type' => 'select', 'required' => true, 'options' => $roadOptions],
                    ['key' => 'people_stranded', 'label' => 'People stranded', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 999999, 'step' => 1, 'default' => '0'],
                    ['key' => 'evacuation_status', 'label' => 'Evacuation status', 'type' => 'select', 'required' => true, 'options' => ['Not started', 'In progress', 'Completed', 'People unable to evacuate']],
                ],
            ],
            'Fire' => [
                'group' => 'Fire and dangerous material incidents',
                'effects' => ['House fire', 'Building or business fire', 'Electrical fire', 'Factory fire', 'Vehicle fire'],
                'questions' => [
                    ['key' => 'fire_status', 'label' => 'Fire status', 'type' => 'select', 'required' => true, 'options' => ['Active and spreading', 'Active but contained', 'Fire appears out', 'Unknown']],
                    ['key' => 'smoke_level', 'label' => 'Smoke condition', 'type' => 'select', 'required' => true, 'options' => ['Light smoke', 'Heavy smoke', 'Dark or toxic-looking smoke', 'Too dangerous to observe']],
                    ['key' => 'people_trapped', 'label' => 'People trapped', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 999999, 'step' => 1, 'default' => '0'],
                    ['key' => 'fire_service_status', 'label' => 'Fire responders', 'type' => 'select', 'required' => true, 'options' => ['Not yet notified', 'Notified and on the way', 'Already on scene', 'Unknown']],
                    ['key' => 'access_for_fire_truck', 'label' => 'Fire truck access', 'type' => 'select', 'required' => true, 'options' => ['Clear access', 'Limited access', 'Blocked access', 'Unknown']],
                    ['key' => 'nearby_structures_at_risk', 'label' => 'Nearby buildings are at risk', 'type' => 'checkbox', 'required' => false],
                ],
            ],
            'Wildfire' => [
                'group' => 'Fire and dangerous material incidents',
                'effects' => ['Forest fire', 'Grass fire', 'Brush fire'],
                'questions' => [
                    ['key' => 'fire_status', 'label' => 'Fire status', 'type' => 'select', 'required' => true, 'options' => ['Active and spreading', 'Active but contained', 'Fire appears out', 'Unknown']],
                    ['key' => 'spread_speed', 'label' => 'Observed spread', 'type' => 'select', 'required' => true, 'options' => ['Slow', 'Fast', 'Very fast', 'Unknown']],
                    ['key' => 'distance_to_homes_meters', 'label' => 'Approximate distance to homes, in meters', 'type' => 'number', 'required' => false, 'min' => 0, 'max' => 100000, 'step' => 1, 'placeholder' => 'Leave blank if unknown'],
                    ['key' => 'wind_condition', 'label' => 'Wind condition', 'type' => 'select', 'required' => true, 'options' => ['Calm', 'Light wind', 'Strong wind', 'Unknown']],
                    ['key' => 'road_passability', 'label' => 'Can vehicles use the road?', 'type' => 'select', 'required' => true, 'options' => $roadOptions],
                    ['key' => 'evacuation_needed', 'label' => 'Evacuation is needed', 'type' => 'checkbox', 'required' => false],
                ],
            ],
            'Chemical or gas incident' => [
                'group' => 'Fire and dangerous material incidents',
                'effects' => ['Chemical spill', 'Gas leak', 'Fuel spill', 'Unknown dangerous substance'],
                'questions' => [
                    ['key' => 'substance_name', 'label' => 'Substance name, if confirmed', 'type' => 'text', 'required' => false, 'maxLength' => 120, 'placeholder' => 'Leave blank if unknown', 'help' => 'Do not approach the material to identify it.'],
                    ['key' => 'release_status', 'label' => 'Leak or spill status', 'type' => 'select', 'required' => true, 'options' => ['Still leaking or spreading', 'Release appears stopped', 'Unknown']],
                    ['key' => 'danger_sign', 'label' => 'Main visible danger', 'type' => 'select', 'required' => true, 'options' => ['Strong odor', 'Visible cloud or vapor', 'Fire or explosion', 'People feeling sick', 'Unknown']],
                    ['key' => 'people_with_symptoms', 'label' => 'People with symptoms', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 999999, 'step' => 1, 'default' => '0'],
                    ['key' => 'area_isolated', 'label' => 'Is the area kept clear?', 'type' => 'select', 'required' => true, 'options' => ['Yes', 'No', 'Unknown']],
                    ['key' => 'responders_present', 'label' => 'Emergency responders present', 'type' => 'select', 'required' => true, 'options' => ['Yes', 'No', 'Unknown']],
                ],
            ],
        ];

        $roadByLegend = [
            'Green' => ['Passable', 'Passable with caution', 'Unknown'],
            'Orange' => ['Passable with caution', 'Not passable', 'Passable', 'Unknown'],
            'Red' => ['Not passable', 'Passable with caution', 'Unknown'],
        ];
        $powerByLegend = [
            'Green' => ['Power available', 'Intermittent power', 'Unknown'],
            'Orange' => ['Intermittent power', 'Power outage', 'Power available', 'Unknown'],
            'Red' => ['Power outage', 'Intermittent power', 'Unknown'],
        ];
        $adaptive = [
            'Flood' => [
                'water_trend' => ['Green' => ['Receding', 'Stable'], 'Orange' => ['Stable', 'Rising', 'Receding'], 'Red' => ['Rising', 'Stable']],
                'road_passability' => $roadByLegend,
            ],
            'Typhoon' => [
                'wind_condition' => ['Green' => ['Light winds'], 'Orange' => ['Strong winds moving large branches', 'Light winds'], 'Red' => ['Very strong winds damaging roofs', 'Too dangerous to observe']],
                'rain_condition' => ['Green' => ['No rain', 'Light rain'], 'Orange' => ['Heavy rain', 'Light rain'], 'Red' => ['Very heavy rain', 'Heavy rain']],
                'power_status' => $powerByLegend,
                'road_passability' => $roadByLegend,
            ],
            'Thunderstorm' => [
                'storm_condition' => ['Green' => ['Weakening', 'Already passed'], 'Orange' => ['Steady', 'Getting stronger', 'Weakening'], 'Red' => ['Getting stronger', 'Steady']],
                'visibility' => ['Green' => ['Clear'], 'Orange' => ['Reduced', 'Clear'], 'Red' => ['Very poor', 'Reduced']],
                'local_flooding' => ['Green' => ['No', 'Unknown'], 'Orange' => ['Yes', 'No', 'Unknown'], 'Red' => ['Yes', 'Unknown']],
                'power_status' => $powerByLegend,
                'road_passability' => $roadByLegend,
            ],
            'Earthquake' => [
                'shaking_strength' => ['Green' => ['Weak', 'Light'], 'Orange' => ['Moderate', 'Strong', 'Light'], 'Red' => ['Very strong', 'Severe']],
                'building_damage' => ['Green' => ['No visible damage', 'Minor cracks or fallen objects'], 'Orange' => ['Minor cracks or fallen objects', 'Major structural damage', 'No visible damage'], 'Red' => ['One or more buildings collapsed', 'Major structural damage']],
                'road_bridge_status' => ['Green' => ['Passable', 'Use with caution', 'Unknown'], 'Orange' => ['Use with caution', 'Blocked or damaged', 'Passable', 'Unknown'], 'Red' => ['Blocked or damaged', 'Unknown']],
            ],
            'Landslide' => [
                'landslide_movement' => ['Green' => ['Movement has stopped', 'Unknown'], 'Orange' => ['Movement has stopped', 'Still moving', 'Unknown'], 'Red' => ['Still moving', 'Unknown']],
                'road_passability' => $roadByLegend,
            ],
            'Volcanic eruption' => [
                'ashfall_level' => ['Green' => ['No ashfall', 'Light dusting'], 'Orange' => ['Light dusting', 'Moderate ash buildup'], 'Red' => ['Heavy ash buildup', 'Moderate ash buildup']],
                'visibility' => ['Green' => ['Clear'], 'Orange' => ['Reduced', 'Clear'], 'Red' => ['Very poor', 'Reduced']],
                'evacuation_order' => ['Green' => ['None heard', 'Unknown'], 'Orange' => ['Residents advised to prepare', 'Residents ordered to evacuate', 'Unknown'], 'Red' => ['Residents ordered to evacuate', 'Residents advised to prepare', 'Unknown']],
                'road_passability' => $roadByLegend,
            ],
            'Tsunami' => [
                'wave_status' => ['Green' => ['Waves have passed', 'Unknown'], 'Orange' => ['Sea suddenly receding', 'Sea level rising unusually', 'Unknown'], 'Red' => ['Large waves arriving', 'Sea level rising unusually', 'Sea suddenly receding', 'Unknown']],
                'road_passability' => $roadByLegend,
                'evacuation_status' => ['Green' => ['Completed'], 'Orange' => ['In progress', 'Not started', 'Completed'], 'Red' => ['People unable to evacuate', 'In progress', 'Not started']],
            ],
            'Fire' => [
                'fire_status' => ['Green' => ['Fire appears out', 'Unknown'], 'Orange' => ['Active but contained', 'Active and spreading', 'Unknown'], 'Red' => ['Active and spreading', 'Active but contained', 'Unknown']],
                'smoke_level' => ['Green' => ['Light smoke'], 'Orange' => ['Heavy smoke', 'Light smoke'], 'Red' => ['Dark or toxic-looking smoke', 'Too dangerous to observe', 'Heavy smoke']],
                'fire_service_status' => ['Green' => ['Already on scene', 'Notified and on the way', 'Unknown'], 'Orange' => ['Notified and on the way', 'Already on scene', 'Not yet notified', 'Unknown'], 'Red' => ['Not yet notified', 'Notified and on the way', 'Already on scene', 'Unknown']],
                'access_for_fire_truck' => ['Green' => ['Clear access', 'Limited access', 'Unknown'], 'Orange' => ['Limited access', 'Blocked access', 'Clear access', 'Unknown'], 'Red' => ['Blocked access', 'Limited access', 'Unknown']],
            ],
            'Wildfire' => [
                'fire_status' => ['Green' => ['Fire appears out', 'Unknown'], 'Orange' => ['Active but contained', 'Active and spreading', 'Unknown'], 'Red' => ['Active and spreading', 'Active but contained', 'Unknown']],
                'spread_speed' => ['Green' => ['Slow', 'Unknown'], 'Orange' => ['Fast', 'Slow', 'Unknown'], 'Red' => ['Very fast', 'Fast', 'Unknown']],
                'wind_condition' => ['Green' => ['Calm', 'Light wind', 'Unknown'], 'Orange' => ['Light wind', 'Strong wind', 'Unknown'], 'Red' => ['Strong wind', 'Unknown']],
                'road_passability' => $roadByLegend,
            ],
            'Chemical or gas incident' => [
                'release_status' => ['Green' => ['Release appears stopped', 'Unknown'], 'Orange' => ['Still leaking or spreading', 'Release appears stopped', 'Unknown'], 'Red' => ['Still leaking or spreading', 'Unknown']],
                'danger_sign' => ['Green' => ['Strong odor', 'Unknown'], 'Orange' => ['Visible cloud or vapor', 'Strong odor', 'People feeling sick', 'Unknown'], 'Red' => ['Fire or explosion', 'People feeling sick', 'Visible cloud or vapor', 'Strong odor', 'Unknown']],
                'area_isolated' => ['Green' => ['Yes', 'Unknown'], 'Orange' => ['Yes', 'No', 'Unknown'], 'Red' => ['No', 'Unknown', 'Yes']],
                'responders_present' => ['Green' => ['Yes', 'Unknown'], 'Orange' => ['No', 'Yes', 'Unknown'], 'Red' => ['No', 'Unknown', 'Yes']],
            ],
        ];

        foreach ($catalog as $disasterName => &$definition) {
            foreach ($definition['questions'] as &$question) {
                if (($question['type'] ?? '') !== 'select') continue;
                $question['legendOptions'] = $adaptive[$disasterName][$question['key']] ?? [
                    'Green' => $question['options'],
                    'Orange' => $question['options'],
                    'Red' => $question['options'],
                ];
                $question['legendSensitive'] = isset($adaptive[$disasterName][$question['key']]);
            }
            unset($question);
        }
        unset($definition);

        return $catalog;
    }

    /** @return array<string,list<string>> */
    public static function groups(): array
    {
        $groups = [];
        foreach (self::disasters() as $name => $definition) {
            $groups[$definition['group']][] = $name;
        }
        return $groups;
    }

    /** @return array{groups:array<string,list<string>>,disasters:array<string,array<string,mixed>>} */
    public static function publicCatalog(): array
    {
        return ['groups' => self::groups(), 'disasters' => self::disasters()];
    }

    /** @return list<string> */
    public static function detailKeys(): array
    {
        $keys = ['particular_type'];
        foreach (self::disasters() as $definition) {
            foreach ($definition['questions'] as $question) $keys[] = $question['key'];
        }
        return array_values(array_unique($keys));
    }

    /** @return array<string,mixed> */
    public static function validate(string $legend, string $group, string $disaster, string $effect, array $input): array
    {
        if (!in_array($legend, ['Green', 'Orange', 'Red'], true)) {
            throw new RuntimeException('Choose a valid community status before continuing.');
        }
        $definition = self::disasters()[$disaster] ?? null;
        if ($definition === null || $definition['group'] !== $group) {
            throw new RuntimeException('Choose a valid disaster group and particular disaster.');
        }
        if (!in_array($effect, $definition['effects'], true)) {
            throw new RuntimeException('Choose a valid observed effect for the selected disaster.');
        }

        $details = ['particular_type' => $effect];
        foreach ($definition['questions'] as $question) {
            $key = (string)$question['key'];
            $label = (string)$question['label'];
            $type = (string)$question['type'];
            if ($type === 'checkbox') {
                $details[$key] = isset($input[$key]) && in_array((string)$input[$key], ['1', 'true', 'on', 'yes'], true);
                continue;
            }

            $value = trim((string)($input[$key] ?? ''));
            if ($value === '') {
                if (!empty($question['required'])) throw new RuntimeException('Complete the disaster situation question: ' . $label . '.');
                continue;
            }

            if ($type === 'select') {
                $options = $question['legendOptions'][$legend] ?? $question['options'] ?? [];
                $allowed = array_map(static fn(mixed $option): string => is_array($option) ? (string)$option['value'] : (string)$option, $options);
                if (!in_array($value, $allowed, true)) throw new RuntimeException('Choose a valid answer for ' . $label . '.');
            } elseif ($type === 'number') {
                if (!is_numeric($value)) throw new RuntimeException($label . ' must be a number.');
                $number = (float)$value;
                if (isset($question['min']) && $number < (float)$question['min']) throw new RuntimeException($label . ' is below the allowed value.');
                if (isset($question['max']) && $number > (float)$question['max']) throw new RuntimeException($label . ' is above the allowed value.');
                if ((float)($question['step'] ?? 1) === 1.0 && floor($number) !== $number) throw new RuntimeException($label . ' must be a whole number.');
            } elseif ($type === 'text' && strlen($value) > (int)($question['maxLength'] ?? 255)) {
                throw new RuntimeException($label . ' is longer than allowed.');
            }
            $details[$key] = $value;
        }
        return $details;
    }

    /** @return list<array{key:string,label:string,value:string}> */
    public static function displayDetails(string $disaster, array $details): array
    {
        $definition = self::disasters()[$disaster] ?? null;
        if ($definition === null) return [];
        $rows = [];
        foreach ($definition['questions'] as $question) {
            $key = (string)$question['key'];
            if (!array_key_exists($key, $details)) continue;
            $value = (string)$details[$key];
            if ($question['type'] === 'checkbox') $value = $value === 'true' || $value === '1' ? 'Yes' : 'No';
            if (in_array($key, ['water_level', 'coastal_water_depth'], true) && $value !== '') $value = FloodLevels::describe($value);
            if ($value === '') $value = 'Not provided';
            $rows[] = ['key' => $key, 'label' => (string)$question['label'], 'value' => $value];
        }
        return $rows;
    }
}
