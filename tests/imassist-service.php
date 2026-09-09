<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ImSafe\Contracts\IAssistContextProvider;
use ImSafe\Contracts\IAssistLanguageModel;
use ImSafe\Services\IAssistSafetyGuide;
use ImSafe\Services\IAssistService;
use ImSafe\Services\IAssistContextService;

function expect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

final class FakeIAssistContext implements IAssistContextProvider {
    public int $calls = 0;
    public function __construct(private array $context = []) {}
    public function load(string $message): array { $this->calls++; return $this->context; }
}

final class FakeIAssistModel implements IAssistLanguageModel {
    public int $calls = 0;
    public bool $fail = false;
    public function respond(string $message, array $history, array $context): array {
        $this->calls++;
        if ($this->fail) throw new RuntimeException('Synthetic AI outage.');
        return ['answer' => 'PAGASA reports heavy rain in the stated area. Follow local evacuation instructions.', 'action' => 'announcements'];
    }
}

$guide = new IAssistSafetyGuide();

$context = new FakeIAssistContext();
$model = new FakeIAssistModel();
$service = new IAssistService($guide, $context, $model);
$urgent = $service->answer('Our house is on fire and someone is trapped', []);
expect($urgent['urgent'] === true, 'Immediate danger must be marked urgent.');
expect(str_contains($urgent['reply'], '911'), 'Immediate danger must direct the user to 911.');
expect(($urgent['actions'][0]['url'] ?? '') === 'tel:911', 'Immediate danger must provide the emergency call action.');
expect($context->calls === 0 && $model->calls === 0, 'Emergency guidance must not wait for system data or AI.');

$fireHere = $service->answer('There is a fire here. We need help.', []);
expect($fireHere['urgent'] === true && str_contains($fireHere['reply'], '911'), 'An active fire must receive immediate emergency guidance.');

$unrelated = $service->answer('Write a poem about my favorite shoes', []);
expect($unrelated['informationStatus'] === 'out_of_scope', 'Unrelated questions must be refused.');
expect(str_contains(strtolower($unrelated['reply']), 'disaster'), 'The refusal must explain the disaster-only scope.');
expect($context->calls === 0 && $model->calls === 0, 'Off-topic questions must not call providers or AI.');
expect($guide->isRelated('Tell me about my shoes') === false, 'Short disaster terms must not match inside unrelated words.');
expect($guide->isRelated('Is JavaScript safe?') === false, 'An unrelated use of safe must remain outside the disaster scope.');
expect($guide->isRelated('How can I track my report?') === true, 'Report tracking questions must remain in scope.');
expect($guide->isRelated('Is it safe in Cavite?') === true, 'Location-based disaster safety questions must remain in scope.');
expect($guide->isRelated('Status IMS-ABCDEF12-1234567890ABCDEF') === true, 'A valid iMSafe reference must remain in scope.');

$flood = $guide->fallback('What should I do during a flood?', ['kind' => 'general']);
expect(str_contains(strtolower($flood['answer']), 'higher ground'), 'Flood guidance must direct users to higher ground.');
expect(str_contains(strtolower($flood['answer']), 'floodwater'), 'Flood guidance must warn against entering floodwater.');

$officialContext = [
    'kind' => 'current',
    'informationStatus' => 'verified_official',
    'statusLabel' => 'Official source',
    'facts' => ['pagasa' => ['synopsis' => 'Heavy rain affecting Luzon.']],
    'sources' => [['label' => 'DOST-PAGASA', 'url' => 'https://www.pagasa.dost.gov.ph/weather']],
    'actions' => [['type' => 'announcements', 'label' => 'View announcements', 'url' => 'announcements.php']],
];
$context = new FakeIAssistContext($officialContext);
$model = new FakeIAssistModel();
$service = new IAssistService($guide, $context, $model);
$answer = $service->answer('What are the current weather alerts?', [['role' => 'user', 'content' => 'Hello']]);
expect($answer['informationStatus'] === 'verified_official', 'Official provider data must retain its verification label.');
expect(($answer['sources'][0]['label'] ?? '') === 'DOST-PAGASA', 'Only server-provided sources may be returned.');
expect(($answer['actions'][0]['url'] ?? '') === 'announcements.php', 'System navigation must use an allowlisted local route.');

$model->fail = true;
$fallback = $service->answer('What are the current weather alerts?', []);
expect($fallback['mode'] === 'built_in', 'An AI failure must fall back to the built-in assistant.');
expect($fallback['reply'] !== '', 'The fallback must always return a useful response.');

$contextReflection = new ReflectionClass(IAssistContextService::class);
$contextService = $contextReflection->newInstanceWithoutConstructor();
$safeIncident = $contextReflection->getMethod('safeIncident');
$sampleIncident = [
    'status' => 'received', 'legend' => 'Orange', 'specific_type' => 'Flash flood', 'created_at' => '2026-09-09 10:00:00',
    'barangay_name' => 'Private Barangay', 'municipality_name' => 'Sample City', 'province_name' => 'Sample Province',
    'region_name' => 'Sample Region', 'note' => 'A public update that should require the exact reference.',
];
$genericIncident = $safeIncident->invoke($contextService, $sampleIncident, false);
expect(!array_key_exists('barangay', $genericIncident), 'Generic incident context must not expose barangay details.');
expect(!array_key_exists('latestPublicUpdate', $genericIncident), 'Generic incident context must not expose report updates.');
$exactIncident = $safeIncident->invoke($contextService, $sampleIncident, true);
expect(($exactIncident['barangay'] ?? '') === 'Private Barangay', 'Exact-reference context may include the public barangay.');
expect(array_key_exists('latestPublicUpdate', $exactIncident), 'Exact-reference context may include the public update.');

echo "PASS: iMAssist emergency, scope, privacy, source, action, and fallback contracts.\n";
