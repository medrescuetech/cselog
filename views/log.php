<?php

use CseLog\Http;
use CseLog\Support;

/** @var array<int, array<string, mixed>> $workTypes */
/** @var array<int, array<string, mixed>> $recent */
$defaultType = null;
foreach ($workTypes as $type) {
    if ((int) $type['is_default'] === 1) {
        $defaultType = (int) $type['id'];
    }
}
$defaultType ??= (int) ($workTypes[0]['id'] ?? 0);
?>
<div class="page-head">
    <h1>Log open work</h1>
    <div class="spacer"></div>
    <a class="btn" href="/board">Cancel</a>
</div>

<form method="post" action="/log" class="card" id="log-form" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= Support::e(Http::csrfToken()) ?>">
    <input type="hidden" name="map_id" value="<?= (int) $map['id'] ?>">

    <div class="field">
        <label>Type of work</label>
        <div class="type-grid">
            <?php foreach ($workTypes as $type): ?>
                <label class="<?= (int) $type['id'] === $defaultType ? 'selected' : '' ?>"
                       data-requires-note="<?= (int) $type['requires_note'] ?>">
                    <input type="radio" name="work_type_id" value="<?= (int) $type['id'] ?>"
                           <?= (int) $type['id'] === $defaultType ? 'checked' : '' ?>>
                    <span class="type-dot" style="background:<?= Support::e($type['colour']) ?>"></span><?= Support::e($type['name']) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="field">
        <label for="location">Location</label>
        <div class="combo">
            <input type="text" id="location" placeholder="Start typing — e.g. Vessel T-301, Sump 4…">
            <div class="combo-list" id="location-list" hidden></div>
        </div>
        <input type="hidden" name="location_id" id="location_id">
        <input type="hidden" name="location_mode" id="location_mode" value="existing">
        <div class="hint" id="location-hint">Pick a saved location, or drop a new pin on the map after submitting.</div>
        <div class="chip-row">
            <button type="button" class="chip" id="new-location">＋ New location (drop a pin)</button>
            <?php foreach (array_slice($recent, 0, 5) as $location): ?>
                <button type="button" class="chip recent" data-id="<?= (int) $location['id'] ?>"
                        data-name="<?= Support::e($location['name']) ?>"><?= Support::e($location['name']) ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="field">
        <label for="notes">Notes</label>
        <textarea name="notes" id="notes" placeholder="Who is in, gas test, attendant, anything the next shift needs."></textarea>
    </div>

    <div class="field-row">
        <div class="field">
            <label for="reported_by">Reported by (radio channel / name)</label>
            <input type="text" name="reported_by" id="reported_by" placeholder="Ch.2 – Dave">
        </div>
        <div class="field">
            <label for="opened_at">Time opened</label>
            <input type="datetime-local" name="opened_at" id="opened_at" value="<?= Support::e(Support::localInputValue()) ?>">
            <div class="hint">Pre-filled with now (<?= Support::e(Support::timezone()) ?>). Change it if the call was late.</div>
        </div>
    </div>

    <button class="btn btn-primary btn-lg" type="submit">Log it</button>
</form>

<script>
(function () {
    const nameInput = document.getElementById('location');
    const list = document.getElementById('location-list');
    const hidden = document.getElementById('location_id');
    const mode = document.getElementById('location_mode');
    const hint = document.getElementById('location-hint');
    const notes = document.getElementById('notes');

    CseLog.combo(nameInput, list, hidden, () => {
        mode.value = 'existing';
        hint.textContent = 'Saved location selected.';
    });

    document.getElementById('new-location').addEventListener('click', () => {
        mode.value = 'new';
        hidden.value = '';
        nameInput.value = '';
        nameInput.disabled = true;
        hint.textContent = 'New location — after you submit you will place the pin on the map.';
    });

    document.querySelectorAll('.chip.recent').forEach(chip => {
        chip.addEventListener('click', () => {
            nameInput.disabled = false;
            nameInput.value = chip.dataset.name;
            hidden.value = chip.dataset.id;
            mode.value = 'existing';
            hint.textContent = 'Saved location selected.';
        });
    });

    document.querySelectorAll('.type-grid label').forEach(label => {
        label.addEventListener('click', () => {
            document.querySelectorAll('.type-grid label').forEach(l => l.classList.remove('selected'));
            label.classList.add('selected');
            notes.required = label.dataset.requiresNote === '1';
        });
    });

    document.getElementById('log-form').addEventListener('submit', e => {
        if (mode.value === 'existing' && !hidden.value) {
            e.preventDefault();
            hint.textContent = 'Pick a location from the list, or choose "New location".';
            nameInput.focus();
        }
    });
}());
</script>
