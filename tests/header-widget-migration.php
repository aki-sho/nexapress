<?php
// Pure migration and placement checks; no database or saved design is modified.
define('BASE_PATH', dirname(__DIR__));
spl_autoload_register(static function (string $class): void {
    $path = BASE_PATH . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($path)) require_once $path;
});
require BASE_PATH . '/app/Core/helpers.php';

$checks = 0;
function checkHeader(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    echo 'PASS: ' . $label . "\n";
}
function rejectsHeader(callable $action, string $label): void
{
    try {
        $action();
    } catch (InvalidArgumentException $error) {
        checkHeader(true, $label);
        return;
    }
    throw new RuntimeException('FAIL: ' . $label);
}
$read = new ReflectionMethod(\app\Core\Design::class, 'layout');
$read->setAccessible(true);
$validate = new ReflectionMethod(\app\Core\Design::class, 'widgets');
$validate->setAccessible(true);
$types = static fn(array $list): array => array_column($list, 'type');
$text = ['type' => 'text', 'title' => 'Retained', 'text' => '<script>plain text</script>', 'menu' => 'footer'];

foreach (['default', 'monoedge'] as $theme) {
    $initial = $read->invoke(null, $theme, []);
    checkHeader($initial['headerWidgets'] === true && $types($initial['widgets']['header1']) === ['site_icon', 'site_name'] &&
        $types($initial['widgets']['header2']) === ['menu'] && $types($initial['widgets']['header3']) === ['search'],
        $theme . ': native header becomes editable widgets');
    $legacy = $initial;
    unset($legacy['headerWidgets']);
    foreach (\app\Core\Design::headerWidgetAreas() as $area) $legacy['widgets'][$area] = [];
    $legacy['headerRows'] = \app\Core\Design::headerRowDefaults();
    $legacy['headerRows']['split'] = true;
    $legacy['headerRows']['bottom']['columns'] = 3;
    $legacy['headerRows']['positions'] = [
        'brand' => ['row' => 'bottom', 'column' => 2],
        'menu' => ['row' => 'top', 'column' => 2],
        'search' => ['row' => 'bottom', 'column' => 3],
    ];
    $legacy['header']['logo'] = false;
    $legacy['header']['menu'] = false;
    $legacy['widgets']['header_bottom2'] = [$text];
    $legacy['widgets']['footer2'] = [$text];
    $migrated = $read->invoke(null, $theme, ['themes' => [$theme => $legacy]]);
    checkHeader($types($migrated['widgets']['header_bottom2']) === ['site_name', 'text'] &&
        $types($migrated['widgets']['header_bottom3']) === ['search'] && !$migrated['widgets']['header2'] &&
        $migrated['widgets']['footer2'] === [$text], $theme . ': visibility, positions, order and other widgets survive migration');
    checkHeader($migrated === $read->invoke(null, $theme, ['themes' => [$theme => $migrated]]),
        $theme . ': migration is idempotent');
    $migrated['widgets']['header_bottom2'] = [];
    checkHeader($read->invoke(null, $theme, ['themes' => [$theme => $migrated]])['widgets']['header_bottom2'] === [],
        $theme . ': removed parts never reappear');

    $legacy['headerRows'] = null;
    $legacy['header']['placement'] = 'stack';
    foreach (['logo', 'name', 'menu', 'search'] as $flag) $legacy['header'][$flag] = true;
    $legacy['widgets']['header1'] = array_fill(0, 12, $text);
    $stacked = $read->invoke(null, $theme, ['themes' => [$theme => $legacy]]);
    checkHeader(count($stacked['widgets']['header1']) === 16 &&
        array_slice($types($stacked['widgets']['header1']), 0, 4) === ['site_icon', 'site_name', 'menu', 'search'] &&
        count($validate->invoke(null, $stacked['widgets']['header1'], 'header1')) === 16,
        $theme . ': all 12 legacy widgets and 4 native parts are retained');
    foreach (['logo', 'name', 'menu', 'search'] as $flag) $legacy['header'][$flag] = false;
    checkHeader(count($read->invoke(null, $theme, ['themes' => [$theme => $legacy]])['widgets']['header1']) === 12,
        $theme . ': hidden native parts remain hidden');
}
foreach (\app\Core\Design::headerWidgetAreas() as $area) {
    checkHeader(count($validate->invoke(null, [['type' => 'site_icon'], ['type' => 'site_name']], $area)) === 2,
        $area . ': accepts header types');
}
foreach (['sidebar', 'header_after', 'footer_before', 'footer1', 'footer_above5', 'footer_bottom5'] as $area) {
    foreach (['site_icon', 'site_name'] as $type) {
        rejectsHeader(static fn() => $validate->invoke(null, [['type' => $type]], $area), $area . ': rejects ' . $type);
    }
    checkHeader($types($validate->invoke(null, [['type' => 'search']], $area)) === ['search'], $area . ': permits search');
}
rejectsHeader(static fn() => $validate->invoke(null, array_fill(0, 17, $text), 'header1'), 'header limit enforced');
rejectsHeader(static fn() => $validate->invoke(null, array_fill(0, 13, $text), 'footer1'), 'footer limit enforced');
rejectsHeader(static fn() => $validate->invoke(null, [['type' => '<script>']], 'header1'), 'unknown widget type rejected');
rejectsHeader(static fn() => $validate->invoke(null, ['invalid'], 'header1'), 'malformed widget rejected');
echo $checks . " header migration checks passed.\n";
