<?php
if (!function_exists('chart_panel')) {
    function chart_panel(string $title, array $values, array $colors, string $description = '', string $extraClass = ''): void {
        $high = max(1, ...array_values($values ?: [1]));
        $total = array_sum($values);
        ?>
        <article class="dashboard-panel chart-panel <?= h($extraClass) ?>">
          <div class="panel-title">
            <div><h2><?= h($title) ?></h2><?php if ($description !== ''): ?><p><?= h($description) ?></p><?php endif; ?></div>
            <span class="panel-total"><?= (int)$total ?> total</span>
          </div>
          <?php if (!$values): ?>
            <p class="dashboard-empty">This breakdown will appear after the first report is submitted.</p>
          <?php else: ?>
            <div class="bar-set">
              <?php $i = 0; foreach ($values as $label => $value): $share = $total > 0 ? (int)round(((int)$value / $total) * 100) : 0; ?>
                <div class="bar-row">
                  <div><b><?= h((string)$label) ?></b><span><strong><?= (int)$value ?></strong><small><?= $share ?>%</small></span></div>
                  <span class="bar-track"><i style="width:<?= round(((int)$value / $high) * 100) ?>%;background:<?= h($colors[$i % count($colors)]) ?>"></i></span>
                </div>
              <?php $i++; endforeach; ?>
            </div>
          <?php endif; ?>
        </article>
        <?php
    }
}

if (!function_exists('bi_duration')) {
    function bi_duration(?int $minutes): string {
        if ($minutes === null) return 'No completed events';
        if ($minutes < 60) return $minutes . ' min';
        if ($minutes < 1440) return number_format($minutes / 60, 1) . ' hr';
        return number_format($minutes / 1440, 1) . ' days';
    }
}

if (!function_exists('bi_trend_chart')) {
    function bi_trend_chart(array $days): void {
        if (!$days) {
            echo '<p class="dashboard-empty">Trend data is unavailable for this selection.</p>';
            return;
        }
        $width = 900;
        $height = 310;
        $left = 50;
        $right = 18;
        $top = 24;
        $bottom = 48;
        $plotWidth = $width - $left - $right;
        $plotHeight = $height - $top - $bottom;
        $count = count($days);
        $rawMax = max(1, ...array_map(static fn(array $day): int => (int)($day['count'] ?? 0), $days));
        $scaleMax = max(4, (int)(ceil($rawMax / 4) * 4));
        $slot = $plotWidth / max(1, $count);
        $barWidth = min(28, max(4, $slot * .56));
        $linePoints = [];
        $labelEvery = $count <= 14 ? 1 : ($count <= 31 ? 5 : 15);
        $colors = ['Green' => '#2f9d69', 'Orange' => '#ec9a24', 'Red' => '#d14452'];
        ?>
        <div class="bi-chart-wrap">
          <svg class="bi-combo-chart" viewBox="0 0 <?= $width ?> <?= $height ?>" role="img" aria-labelledby="trend-chart-title trend-chart-desc">
            <title id="trend-chart-title">Incident reports by date and priority</title>
            <desc id="trend-chart-desc">Stacked daily columns show green, orange, and red priority reports. The blue line shows total reports.</desc>
            <defs><linearGradient id="trend-area-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#236ca1" stop-opacity=".18"/><stop offset="1" stop-color="#236ca1" stop-opacity="0"/></linearGradient></defs>
            <?php for ($tick = 0; $tick <= 4; $tick++): $value = (int)round($scaleMax * (4 - $tick) / 4); $y = $top + ($plotHeight * $tick / 4); ?>
              <line class="chart-grid-line" x1="<?= $left ?>" y1="<?= $y ?>" x2="<?= $width - $right ?>" y2="<?= $y ?>"/>
              <text class="chart-axis-label" x="<?= $left - 10 ?>" y="<?= $y + 4 ?>" text-anchor="end"><?= $value ?></text>
            <?php endfor; ?>
            <?php foreach (array_values($days) as $index => $day):
                $center = $left + ($slot * $index) + ($slot / 2);
                $baseline = $top + $plotHeight;
                $total = (int)($day['count'] ?? 0);
                $lineY = $baseline - (($total / $scaleMax) * $plotHeight);
                $linePoints[] = number_format($center, 2, '.', '') . ',' . number_format($lineY, 2, '.', '');
                $stackY = $baseline;
            ?>
              <g class="chart-day"><title><?= h((string)($day['shortDate'] ?? '')) ?>: <?= $total ?> total; <?= (int)($day['Red'] ?? 0) ?> red, <?= (int)($day['Orange'] ?? 0) ?> orange, <?= (int)($day['Green'] ?? 0) ?> green</title>
                <?php foreach (['Green', 'Orange', 'Red'] as $legend): $value = (int)($day[$legend] ?? 0); if ($value === 0) continue; $segmentHeight = ($value / $scaleMax) * $plotHeight; $stackY -= $segmentHeight; ?>
                  <rect x="<?= number_format($center - ($barWidth / 2), 2, '.', '') ?>" y="<?= number_format($stackY, 2, '.', '') ?>" width="<?= number_format($barWidth, 2, '.', '') ?>" height="<?= max(2, $segmentHeight) ?>" fill="<?= $colors[$legend] ?>"/>
                <?php endforeach; ?>
                <?php if ($index % $labelEvery === 0 || $index === $count - 1): ?><text class="chart-axis-label chart-x-label" x="<?= number_format($center, 2, '.', '') ?>" y="<?= $height - 20 ?>" text-anchor="middle"><?= h((string)($day['shortDate'] ?? '')) ?></text><?php endif; ?>
              </g>
            <?php endforeach; ?>
            <?php $firstPoint = explode(',', $linePoints[0]); $lastPoint = explode(',', $linePoints[count($linePoints) - 1]); $areaPoints = $left . ',' . ($top + $plotHeight) . ' ' . implode(' ', $linePoints) . ' ' . ($width - $right) . ',' . ($top + $plotHeight); ?>
            <polygon class="trend-area" points="<?= h($areaPoints) ?>"/>
            <polyline class="trend-total-line" points="<?= h(implode(' ', $linePoints)) ?>"/>
            <?php foreach ($linePoints as $point): [$x, $y] = explode(',', $point); ?><circle class="trend-point" cx="<?= $x ?>" cy="<?= $y ?>" r="3.5"/><?php endforeach; ?>
          </svg>
        </div>
        <div class="bi-chart-legend" aria-label="Chart legend"><span class="total">Total</span><span class="green">Green</span><span class="orange">Orange</span><span class="red">Red</span></div>
        <?php
    }
}
