<?php
if (!function_exists('chart_panel')) {
    function chart_panel(string $title, array $values, array $colors, string $description = ''): void {
        $high = max(1, ...array_values($values ?: [1]));
        $total = array_sum($values);
        ?>
        <article class="dashboard-panel chart-panel">
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
