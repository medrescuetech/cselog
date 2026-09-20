<?php

use CseLog\Support;
?>
<div class="page-head"><h1>Admin</h1></div>

<div class="stat-row">
    <div class="stat"><b><?= (int) $stats['open'] ?></b><span>open now</span></div>
    <div class="stat"><b><?= (int) $stats['entries'] ?></b><span>entries all time</span></div>
    <div class="stat"><b><?= (int) $stats['locations'] ?></b><span>saved locations</span></div>
    <div class="stat"><b><?= (int) $stats['unverified'] ?></b><span>awaiting verification</span></div>
    <div class="stat"><b><?= (int) $stats['adhoc_share'] ?>%</b><span>logged as ad-hoc pins</span></div>
    <div class="stat"><b><?= (int) $stats['work_types'] ?></b><span>active work types</span></div>
    <div class="stat"><b><?= (int) $stats['users'] ?></b><span>active users</span></div>
</div>

<div class="card">
    <h2>Manage</h2>
    <p><a href="/admin/locations">Locations</a> — verify new pins, rename, alias, merge duplicates, archive.</p>
    <p><a href="/admin/work-types">Work types</a> — the list on the log form, its default and colours.</p>
    <p><a href="/admin/maps">Maps</a> — upload the real site plan and switch over from the placeholder.</p>
    <p><a href="/admin/overlays">Landmarks &amp; boundaries</a> — draw areas and drop landmarks on the map.</p>
    <p><a href="/admin/users">Users</a> — accounts and roles.</p>
</div>

<div class="card">
    <h2>Health check</h2>
    <p class="hint">
        A high ad-hoc share means people are not finding saved locations — check the names in
        <a href="/admin/locations?filter=unverified">unverified</a> and merge duplicates.
        Site: <?= Support::e($site['name']) ?> (<?= Support::e($site['timezone']) ?>).
    </p>
</div>
