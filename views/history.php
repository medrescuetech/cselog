<?php

use CseLog\Support;

/** @var array<int, array<string, mixed>> $rows */
/** @var array<string, string> $filters */
$query = http_build_query(array_filter($filters, static fn (string $v): bool => $v !== ''));
?>
<div class="page-head">
    <h1>History</h1>
    <div class="spacer"></div>
    <a class="btn" href="/history/export.csv<?= $query === '' ? '' : '?' . Support::e($query) ?>">Export CSV</a>
</div>

<form class="card" method="get" action="/history">
    <div class="field-row">
        <div class="field">
            <label for="from">From</label>
            <input type="date" name="from" id="from" value="<?= Support::e($filters['from']) ?>">
        </div>
        <div class="field">
            <label for="to">To</label>
            <input type="date" name="to" id="to" value="<?= Support::e($filters['to']) ?>">
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select name="status" id="status">
                <?php foreach (['' => 'Any', 'open' => 'Open', 'closed' => 'Closed', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="work_type_id">Work type</label>
            <select name="work_type_id" id="work_type_id">
                <option value="">Any</option>
                <?php foreach ($workTypes as $type): ?>
                    <option value="<?= (int) $type['id'] ?>" <?= $filters['work_type_id'] === (string) $type['id'] ? 'selected' : '' ?>>
                        <?= Support::e($type['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="area_id">Area</label>
            <select name="area_id" id="area_id">
                <option value="">Any</option>
                <?php foreach ($areas as $area): ?>
                    <option value="<?= (int) $area['id'] ?>" <?= $filters['area_id'] === (string) $area['id'] ? 'selected' : '' ?>>
                        <?= Support::e($area['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="q">Search location or notes</label>
            <input type="search" name="q" id="q" value="<?= Support::e($filters['q']) ?>">
        </div>
    </div>
    <button class="btn btn-primary" type="submit">Apply</button>
    <a class="btn btn-ghost" href="/history">Reset</a>
</form>

<div class="stat-row">
    <div class="stat"><b><?= (int) $summary['count'] ?></b><span>entries shown</span></div>
    <div class="stat"><b><?= (int) $summary['open'] ?></b><span>still open</span></div>
    <div class="stat"><b><?= Support::e($summary['median_duration']) ?></b><span>median duration</span></div>
    <div class="stat"><b><?= Support::e($summary['longest']) ?></b><span>longest</span></div>
</div>

<div class="card">
    <table>
        <thead>
        <tr>
            <th>#</th><th>Location</th><th>Area</th><th>Work type</th>
            <th>Opened</th><th>Closed</th><th>Duration</th><th>Notes</th><th>Status</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><a href="/entries/<?= (int) $row['id'] ?>"><?= (int) $row['id'] ?></a></td>
                <td><?= Support::e($row['location_label']) ?></td>
                <td class="muted"><?= Support::e($row['area_name'] ?? '—') ?></td>
                <td><?= Support::e($row['work_type']) ?></td>
                <td><?= Support::e(Support::local((string) $row['opened_at'], 'j M, H:i')) ?></td>
                <td><?= $row['closed_at'] === null ? '—' : Support::e(Support::local((string) $row['closed_at'], 'j M, H:i')) ?></td>
                <td><?= Support::e($row['duration_human']) ?></td>
                <td class="muted"><?= Support::e(mb_strimwidth((string) ($row['notes'] ?? ''), 0, 70, '…')) ?></td>
                <td><span class="pill pill-<?= $row['status'] === 'open' ? Support::e($row['age_level']) : 'green' ?>"><?= Support::e($row['status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <tr><td colspan="9" class="empty">Nothing matches those filters.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php if (count($rows) >= 500): ?>
        <p class="hint">Showing the most recent 500 — narrow the dates, or export the CSV for the full set.</p>
    <?php endif; ?>
</div>
