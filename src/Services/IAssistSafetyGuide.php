<?php
declare(strict_types=1);

namespace ImSafe\Services;

final class IAssistSafetyGuide
{
    private const DISASTER_TERMS = [
        'disaster', 'emergency', 'alert', 'warning', 'incident', 'hazard', 'safety', 'prepare', 'evacuate', 'evacuation', 'evacuating', 'rescue',
        'flood', 'floodwater', 'fire', 'smoke', 'earthquake', 'aftershock', 'typhoon', 'cyclone', 'storm', 'rain', 'lightning',
        'tsunami', 'landslide', 'mudflow', 'volcano', 'volcanic', 'ashfall', 'lahar', 'chemical', 'gas leak', 'power outage',
        'pagasa', 'phivolcs', 'gdacs', 'ndrrmc', 'weather', 'hotline',
        'baha', 'sunog', 'lindol', 'bagyo', 'ulan', 'pagguho', 'bulkan', 'abo', 'sakuna', 'panganib', 'lumikas', 'tulong',
    ];

    public function emergency(string $message): ?array
    {
        $text = $this->normalize($message);
        $signals = [
            'on fire', 'fire here', 'there is a fire', 'house is burning', 'building is burning', 'may sunog', 'nasusunog',
            'trapped', 'na trap', 'cannot get out', "can't get out", 'cant get out',
            'is injured', 'am injured', 'injured person', 'badly injured', 'seriously injured', 'need an ambulance', 'bleeding', 'dumudugo', 'cannot breathe', "can't breathe", 'cant breathe', 'unconscious',
            'water is rising', 'flood is rising', 'tumataas ang baha', 'swept away', 'drowning', 'nalulunod',
            'earthquake now', 'shaking now', 'lindol ngayon', 'tsunami coming', 'tsunami warning here',
            'landslide happening', 'guho ngayon', 'gas leak now', 'chemical spill now', 'explosion',
            'immediate danger', 'life threatening', 'need rescue now', 'send help now', 'help us now',
        ];
        if (!$this->containsAny($text, $signals)) return null;

        $hazard = $this->hazard($text);
        $instruction = match ($hazard) {
            'fire' => 'Leave the building immediately if you can. Stay low under smoke, do not use an elevator, and do not go back inside.',
            'flood' => 'Move to higher ground or a higher floor now. Do not walk, swim, or drive through floodwater. Keep away from electrical equipment.',
            'earthquake' => 'If the ground is shaking, Drop, Cover, and Hold On. After shaking stops, move away from damaged buildings if the route is safe.',
            'tsunami' => 'Move inland and to high ground immediately. Do not wait near the coast, rivers, or estuaries for visual confirmation.',
            'landslide' => 'Move away from the path of the slide toward stable, higher ground. Avoid slopes, river channels, and damaged roads.',
            'volcano' => 'Follow the evacuation order immediately. Move away from restricted zones and river channels that may carry lahars.',
            'hazmat' => 'Move away from the spill or gas cloud. Go upwind if possible, avoid touching the substance, and do not create sparks.',
            default => 'Move away from the immediate danger if you can do so safely. Follow evacuation or responder instructions without delay.',
        };

        return [
            'answer' => 'This may be life-threatening. Call 911 now and give your exact location, the hazard, and the number of people affected. ' . $instruction . ' If you cannot call, ask someone nearby to call for you.',
            'informationStatus' => 'emergency_guidance',
            'statusLabel' => 'Immediate safety guidance',
            'sources' => [['label' => 'Philippine National Emergency Hotline', 'url' => 'https://ehotlines.e.gov.ph/']],
            'actions' => [
                ['type' => 'emergency', 'label' => 'Call 911', 'url' => 'tel:911'],
                ['type' => 'report', 'label' => 'Report emergency', 'url' => 'report.php'],
            ],
        ];
    }

    public function isRelated(string $message): bool
    {
        $text = $this->normalize($message);
        if ($text === '') return false;
        if (preg_match('/\bIMS-[A-F0-9]{8}-[A-F0-9]{16}\b/i', $message) === 1) return true;
        if (in_array($text, ['hello', 'hi', 'hi there', 'hello imassist', 'kumusta', 'what can you do', 'help', 'help me'], true)) return true;
        if ($this->containsAny($text, ['imsafe', 'my report', 'report an incident', 'report incident', 'report emergency', 'track report', 'reference code', 'request assistance', 'need assistance', 'current conditions', 'is it safe in', 'is it safe here', 'safe ba sa', 'ligtas ba'])) return true;
        return $this->containsAny($text, self::DISASTER_TERMS);
    }

    public function fallback(string $message, array $context): array
    {
        $text = $this->normalize($message);
        $summary = trim((string)($context['summary'] ?? ''));
        if ($summary !== '') return ['answer' => $summary, 'action' => (string)($context['preferredAction'] ?? 'none')];

        if ($this->containsAny($text, ['track', 'reference', 'status of my report'])) {
            return ['answer' => 'Use Track report and enter the complete reference code you received after submitting. I cannot confirm a report without that code.', 'action' => 'track'];
        }
        if ($this->containsAny($text, ['report', 'request assistance', 'need assistance', 'send help'])) {
            return ['answer' => 'Open Report emergency and provide the observed hazard, exact location, current condition, and any immediate needs. If anyone is in immediate danger, call 911 first.', 'action' => 'report'];
        }

        $hazard = $this->hazard($text);
        $phase = $this->containsAny($text, ['before', 'prepare', 'preparedness', 'kit']) ? 'before'
            : ($this->containsAny($text, ['after', 'return', 'cleanup']) ? 'after' : 'during');
        $answer = $this->guidance($hazard, $phase);
        if ($answer !== '') return ['answer' => $answer, 'action' => 'announcements'];

        if (($context['informationStatus'] ?? '') === 'information_unavailable') {
            return ['answer' => 'I cannot confirm current conditions from the available system data. Check Announcements and follow instructions from PAGASA, PHIVOLCS, NDRRMC, and your local government.', 'action' => 'announcements'];
        }
        return ['answer' => 'I can help with disaster alerts, safety steps, preparedness, evacuation guidance, incident reporting, and report tracking. Tell me the hazard and your city or municipality if location matters.', 'action' => 'none'];
    }

    private function guidance(string $hazard, string $phase): string
    {
        if ($phase === 'before') return match ($hazard) {
            'flood' => 'Before flooding, know your evacuation route, prepare medicines and documents in a waterproof bag, charge phones, and monitor PAGASA and local warnings. Move early if authorities advise evacuation.',
            'fire' => 'Before a fire, keep exits clear, test smoke alarms, store emergency numbers, and agree on an outdoor meeting place. Never overload outlets or leave open flames unattended.',
            'earthquake' => 'Before an earthquake, secure heavy furniture, identify safe cover, prepare supplies, and practice Drop, Cover, and Hold On with your household.',
            'tsunami' => 'Before a tsunami, learn whether your area is in an evacuation zone and practice the quickest route inland or to high ground. Keep a portable emergency kit ready.',
            'landslide' => 'Before a landslide, watch for cracks, leaning trees, unusual rumbling, and changes in water flow. Know a route away from slopes and river channels.',
            'volcano' => 'Before volcanic activity, know official evacuation zones, prepare masks and eye protection, sealable water and food, medicines, and a route away from river channels.',
            'typhoon' => 'Before a typhoon, monitor PAGASA, secure loose outdoor objects, charge devices, store safe water and food, and prepare to evacuate if your local government instructs you.',
            default => 'Prepare a household emergency plan, evacuation routes, contact arrangements, medicines, water, food, lights, radio, power banks, and copies of important documents. Follow official local warnings.',
        };

        if ($phase === 'after') return match ($hazard) {
            'flood' => 'After a flood, return only when authorities permit it. Avoid standing water, damaged electrical systems, unstable structures, and food or water that may be contaminated.',
            'fire' => 'After a fire, do not re-enter until firefighters allow it. Avoid damaged wiring and structures, seek medical care for burns or smoke exposure, and document damage safely.',
            'earthquake' => 'After an earthquake, expect aftershocks. Leave damaged structures, check for injuries and gas leaks without using flames, and follow PHIVOLCS and local instructions.',
            'tsunami' => 'After a tsunami, stay on high ground until authorities give the all-clear. More waves may follow, and damaged coastal areas may remain dangerous.',
            'landslide' => 'After a landslide, stay away from the slide area because additional movement may occur. Report broken utilities and do not cross damaged slopes or roads.',
            'volcano' => 'After volcanic activity, remain outside restricted zones. During ashfall cleanup, wear a well-fitting mask and eye protection, lightly dampen ash, and avoid overloading roofs.',
            default => 'After a disaster, wait for official instructions before returning. Check for injuries and hazards, avoid damaged utilities and structures, and use safe water and food.',
        };

        return match ($hazard) {
            'flood' => 'Move to higher ground or a higher floor. Do not walk, swim, or drive through floodwater, and stay off bridges over fast-moving water. Evacuate immediately when authorities instruct you.',
            'fire' => 'Leave immediately using the nearest safe exit. Stay low under smoke, do not use elevators, and never return for belongings. Once outside, call 911 and stay away from the building.',
            'earthquake' => 'Drop to your hands and knees, cover your head and neck under sturdy furniture, and hold on. Stay away from windows. After shaking, watch for aftershocks and damaged structures.',
            'tsunami' => 'After strong or long coastal shaking, a sudden sea change, or an official warning, move inland and to high ground immediately. Stay there until authorities give the all-clear.',
            'landslide' => 'Move away from the slide path toward stable ground. Listen for rumbling, watch for falling debris, avoid river channels, and do not cross a damaged slope or road.',
            'volcano' => 'Follow PHIVOLCS and local evacuation instructions. Stay outside restricted zones. During ashfall, remain indoors when possible, close openings, and use a well-fitting mask and eye protection outside.',
            'typhoon' => 'Stay indoors away from windows, monitor PAGASA and local instructions, keep devices charged, and do not travel through flooded roads. Evacuate early when directed.',
            'hazmat' => 'Move away and upwind from the substance. Do not touch or smell it, avoid flames and switches near a gas leak, and call 911 from a safe location.',
            default => '',
        };
    }

    private function hazard(string $text): string
    {
        return match (true) {
            $this->containsAny($text, ['flood', 'floodwater', 'baha', 'drowning', 'nalulunod']) => 'flood',
            $this->containsAny($text, ['fire', 'smoke', 'sunog', 'nasusunog']) => 'fire',
            $this->containsAny($text, ['earthquake', 'aftershock', 'shaking', 'lindol']) => 'earthquake',
            str_contains($text, 'tsunami') => 'tsunami',
            $this->containsAny($text, ['landslide', 'mudflow', 'pagguho', 'guho']) => 'landslide',
            $this->containsAny($text, ['volcano', 'volcanic', 'ashfall', 'lahar', 'bulkan', 'abo']) => 'volcano',
            $this->containsAny($text, ['typhoon', 'cyclone', 'storm', 'bagyo']) => 'typhoon',
            $this->containsAny($text, ['chemical', 'hazardous material', 'gas leak', 'spill']) => 'hazmat',
            default => 'general',
        };
    }

    private function normalize(string $value): string
    {
        return strtolower(trim((string)preg_replace('/\s+/', ' ', $value)));
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (preg_match('/(?<![a-z0-9])' . preg_quote($needle, '/') . '(?![a-z0-9])/i', $text) === 1) return true;
        }
        return false;
    }
}
