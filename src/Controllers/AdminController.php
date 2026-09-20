<?php

declare(strict_types=1);

namespace CseLog\Controllers;

use CseLog\Auth;
use CseLog\Db;
use CseLog\Http;
use CseLog\Repo;
use CseLog\Support;

final class AdminController
{
    public function index(): void
    {
        $site = Repo::site();

        Http::render('admin/index', [
            'title' => 'Admin',
            'site' => $site,
            'stats' => [
                'open' => (int) Db::value("SELECT COUNT(*) FROM entries WHERE site_id = ? AND status = 'open'", [(int) $site['id']]),
                'entries' => (int) Db::value('SELECT COUNT(*) FROM entries WHERE site_id = ?', [(int) $site['id']]),
                'locations' => (int) Db::value("SELECT COUNT(*) FROM locations WHERE site_id = ? AND status = 'active'", [(int) $site['id']]),
                'unverified' => (int) Db::value("SELECT COUNT(*) FROM locations WHERE site_id = ? AND status = 'active' AND verified = 0", [(int) $site['id']]),
                'adhoc_share' => $this->adHocShare((int) $site['id']),
                'work_types' => (int) Db::value('SELECT COUNT(*) FROM work_types WHERE active = 1'),
                'users' => (int) Db::value('SELECT COUNT(*) FROM users WHERE active = 1'),
            ],
        ]);
    }

    // ── Work types ──────────────────────────────────────────────────────────

    public function workTypes(): void
    {
        Http::render('admin/work-types', [
            'title' => 'Work types',
            'workTypes' => Repo::workTypes(false),
        ]);
    }

    public function workTypeStore(): void
    {
        $name = trim((string) Http::input('name', ''));

        if ($name === '') {
            Http::flash('Give the work type a name.', 'error');
            Http::redirect('/admin/work-types');
        }

        Db::insert('work_types', [
            'name' => $name,
            'colour' => Http::input('colour', '#6b7785'),
            'is_default' => 0,
            'requires_note' => Http::input('requires_note') === '1' ? 1 : 0,
            'sort_order' => (int) (Db::value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM work_types') ?? 1),
            'active' => 1,
        ]);

        Http::flash('Added: ' . $name);
        Http::redirect('/admin/work-types');
    }

    public function workTypeUpdate(array $params): void
    {
        $id = (int) $params['id'];
        $action = (string) Http::input('action', 'save');

        if ($action === 'default') {
            Db::run('UPDATE work_types SET is_default = 0');
            Db::update('work_types', ['is_default' => 1, 'active' => 1], ['id' => $id]);
            Http::flash('Default work type updated.');
        } elseif ($action === 'toggle') {
            $current = Db::first('SELECT active FROM work_types WHERE id = ?', [$id]);
            Db::update('work_types', ['active' => (int) $current['active'] === 1 ? 0 : 1], ['id' => $id]);
            Http::flash('Work type ' . ((int) $current['active'] === 1 ? 'retired (history kept)' : 'reactivated') . '.');
        } elseif ($action === 'move') {
            $direction = Http::input('direction') === 'up' ? -15 : 15;
            $current = Db::first('SELECT sort_order FROM work_types WHERE id = ?', [$id]);
            Db::update('work_types', ['sort_order' => (int) $current['sort_order'] + $direction], ['id' => $id]);
            $this->resequenceWorkTypes();
        } else {
            Db::update('work_types', [
                'name' => (string) Http::input('name'),
                'colour' => (string) Http::input('colour'),
                'requires_note' => Http::input('requires_note') === '1' ? 1 : 0,
            ], ['id' => $id]);
            Http::flash('Saved.');
        }

        Http::redirect('/admin/work-types');
    }

    private function resequenceWorkTypes(): void
    {
        $order = 0;
        foreach (Db::all('SELECT id FROM work_types ORDER BY sort_order, name') as $row) {
            Db::update('work_types', ['sort_order' => $order += 10], ['id' => (int) $row['id']]);
        }
    }

    // ── Maps ────────────────────────────────────────────────────────────────

    public function maps(): void
    {
        Http::render('admin/maps', [
            'title' => 'Maps',
            'maps' => Db::all('SELECT * FROM maps ORDER BY is_default DESC, name'),
            'site' => Repo::site(),
        ]);
    }

    /** Uploads a replacement site image (the real survey map). */
    public function mapStore(): void
    {
        $site = Repo::site();
        $name = trim((string) Http::input('name', 'Site plan'));
        $file = $_FILES['image'] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Http::flash('Choose an image file to upload.', 'error');
            Http::redirect('/admin/maps');
        }

        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/svg+xml' => 'svg', 'image/webp' => 'webp'];
        $mime = (string) (mime_content_type($file['tmp_name']) ?: '');

        if (!isset($allowed[$mime])) {
            Http::flash('Unsupported image type (' . $mime . '). Use PNG, JPEG, WebP or SVG.', 'error');
            Http::redirect('/admin/maps');
        }

        // An SVG is a document, not just pixels: served from our own origin it
        // would run any script it carries. Reject anything active outright.
        if ($mime === 'image/svg+xml' && self::svgIsActive((string) file_get_contents($file['tmp_name']))) {
            Http::flash('That SVG contains scripting or external references. Export it as PNG instead.', 'error');
            Http::redirect('/admin/maps');
        }

        $width = Http::intInput('width_px');
        $height = Http::intInput('height_px');

        if ($mime !== 'image/svg+xml') {
            $size = getimagesize($file['tmp_name']);
            if ($size !== false) {
                [$width, $height] = [$size[0], $size[1]];
            }
        }

        if ($width === null || $height === null || $width < 1 || $height < 1) {
            Http::flash('Could not read the image size — enter the width and height in pixels.', 'error');
            Http::redirect('/admin/maps');
        }

        $filename = Support::slug($name) . '-' . time() . '.' . $allowed[$mime];
        $target = dirname(__DIR__, 2) . '/public/maps/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            Http::flash('Upload failed — check that public/maps is writable.', 'error');
            Http::redirect('/admin/maps');
        }

        $mapId = Db::insert('maps', [
            'site_id' => (int) $site['id'],
            'name' => $name,
            'image_path' => '/maps/' . $filename,
            'width_px' => $width,
            'height_px' => $height,
            'crs' => 'simple',
            'is_default' => 0,
            'active' => 1,
            'created_at' => Support::nowUtc(),
        ]);

        Http::flash('Map uploaded. Set it as default when you are ready to switch over.');
        Http::redirect('/admin/maps?map=' . $mapId);
    }

    /** True when an SVG carries script, event handlers or remote references. */
    private static function svgIsActive(string $svg): bool
    {
        return preg_match(
            '/<\s*(script|foreignObject|iframe|embed|object|handler|set|animate)\b|\bon[a-z]+\s*=|javascript:|<!ENTITY|xlink:href\s*=\s*["\']\s*(?!#)/i',
            $svg
        ) === 1;
    }

    public function mapUpdate(array $params): void
    {
        $id = (int) $params['id'];
        $action = (string) Http::input('action', 'save');

        if ($action === 'default') {
            Db::run('UPDATE maps SET is_default = 0');
            Db::update('maps', ['is_default' => 1, 'active' => 1], ['id' => $id]);
            Http::flash('Default map updated. New pins will use it.');
        } elseif ($action === 'toggle') {
            $hidden = Db::transaction(static function () use ($id): bool {
                $map = Db::first('SELECT active, is_default FROM maps WHERE id = ?', [$id]);
                if ($map === null) {
                    Http::notFound('Map not found.');
                }

                if ((int) $map['active'] === 0) {
                    Db::update('maps', ['active' => 1], ['id' => $id]);

                    return true;
                }

                // The app resolves an active map on nearly every screen, so the
                // default (or last remaining) map cannot be hidden.
                $othersActive = (int) Db::value('SELECT COUNT(*) FROM maps WHERE active = 1 AND id <> ?', [$id]);

                if ((int) $map['is_default'] === 1 || $othersActive === 0) {
                    return false;
                }

                Db::update('maps', ['active' => 0], ['id' => $id]);

                return true;
            });

            Http::flash(
                $hidden
                    ? 'Map visibility updated.'
                    : 'Make another map the default first — the app needs one active map.',
                $hidden ? 'ok' : 'error'
            );
        } else {
            Db::update('maps', ['name' => (string) Http::input('name')], ['id' => $id]);
            Http::flash('Saved.');
        }

        Http::redirect('/admin/maps');
    }

    // ── Overlays: landmarks and boundaries ──────────────────────────────────

    public function overlays(): void
    {
        $map = Repo::map(Http::intInput('map'));

        Http::render('admin/overlays', [
            'title' => 'Landmarks & boundaries',
            'map' => $map,
            'maps' => Repo::maps(),
            'areas' => Repo::areas((int) $map['id']),
            'landmarks' => Repo::landmarks((int) $map['id']),
        ]);
    }

    public function areaStore(): void
    {
        $map = Repo::map(Http::intInput('map_id'));
        $name = trim((string) Http::input('name', ''));
        $rings = json_decode((string) Http::input('rings', '[]'), true);

        if ($name === '' || !is_array($rings) || count($rings) === 0 || count($rings[0]) < 3) {
            Http::json(['ok' => false, 'error' => 'A boundary needs a name and at least three points.'], 422);
        }

        $id = Http::intInput('id');

        if ($id !== null) {
            Db::update('areas', [
                'name' => $name,
                'kind' => Http::input('kind', 'zone'),
                'colour' => Http::input('colour', '#2b7fd9'),
                'geometry_px' => json_encode($rings),
            ], ['id' => $id]);
        } else {
            $id = Db::insert('areas', [
                'map_id' => (int) $map['id'],
                'name' => $name,
                'kind' => Http::input('kind', 'zone'),
                'colour' => Http::input('colour', '#2b7fd9'),
                'geometry_px' => json_encode($rings),
                'active' => 1,
                'created_at' => Support::nowUtc(),
            ]);
        }

        Http::json(['ok' => true, 'id' => $id]);
    }

    public function areaDelete(array $params): void
    {
        $area = Db::first('SELECT map_id FROM areas WHERE id = ?', [(int) $params['id']]);
        Db::update('areas', ['active' => 0], ['id' => (int) $params['id']]);

        Http::flash('Boundary removed.');
        Http::redirect('/admin/overlays?map=' . (int) ($area['map_id'] ?? 0));
    }

    public function landmarkStore(): void
    {
        $map = Repo::map(Http::intInput('map_id'));
        $name = trim((string) Http::input('name', ''));
        $x = Http::floatInput('x');
        $y = Http::floatInput('y');

        if ($name === '' || $x === null || $y === null) {
            Http::json(['ok' => false, 'error' => 'A landmark needs a name and a position.'], 422);
        }

        $id = Http::intInput('id');

        if ($id !== null) {
            Db::update('landmarks', [
                'name' => $name,
                'category' => Http::input('category'),
                'colour' => Http::input('colour', '#5a6b78'),
                'notes' => Http::input('notes'),
                'x' => $x,
                'y' => $y,
            ], ['id' => $id]);
        } else {
            $id = Db::insert('landmarks', [
                'map_id' => (int) $map['id'],
                'name' => $name,
                'category' => Http::input('category'),
                'colour' => Http::input('colour', '#5a6b78'),
                'notes' => Http::input('notes'),
                'x' => $x,
                'y' => $y,
                'active' => 1,
                'created_at' => Support::nowUtc(),
            ]);
        }

        Http::json(['ok' => true, 'id' => $id]);
    }

    public function landmarkDelete(array $params): void
    {
        $landmark = Db::first('SELECT map_id FROM landmarks WHERE id = ?', [(int) $params['id']]);
        Db::update('landmarks', ['active' => 0], ['id' => (int) $params['id']]);

        Http::flash('Landmark removed.');
        Http::redirect('/admin/overlays?map=' . (int) ($landmark['map_id'] ?? 0));
    }

    // ── Users ───────────────────────────────────────────────────────────────

    public function users(): void
    {
        Http::render('admin/users', [
            'title' => 'Users',
            'users' => Db::all('SELECT * FROM users ORDER BY active DESC, display_name'),
            'roles' => Auth::roles(),
        ]);
    }

    public function userStore(): void
    {
        $username = strtolower(trim((string) Http::input('username', '')));
        $password = (string) Http::input('password', '');

        if ($username === '' || strlen($password) < 8) {
            Http::flash('Username required, and a password of at least 8 characters.', 'error');
            Http::redirect('/admin/users');
        }

        if (Db::first('SELECT id FROM users WHERE username = ?', [$username]) !== null) {
            Http::flash('That username already exists.', 'error');
            Http::redirect('/admin/users');
        }

        Db::insert('users', [
            'username' => $username,
            'display_name' => (string) Http::input('display_name', $username),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => in_array(Http::input('role'), Auth::roles(), true) ? (string) Http::input('role') : 'logger',
            'active' => 1,
            'created_at' => Support::nowUtc(),
        ]);

        Http::flash('User added.');
        Http::redirect('/admin/users');
    }

    public function userUpdate(array $params): void
    {
        $id = (int) $params['id'];
        $user = Db::first('SELECT * FROM users WHERE id = ?', [$id]);

        if ($user === null) {
            Http::notFound('User not found.');
        }

        $action = (string) Http::input('action', 'save');

        if ($action === 'toggle') {
            if ($id === Auth::id()) {
                Http::flash('You cannot deactivate your own account.', 'error');
                Http::redirect('/admin/users');
            }
            Db::update('users', ['active' => (int) $user['active'] === 1 ? 0 : 1], ['id' => $id]);
        } elseif ($action === 'password') {
            $password = (string) Http::input('password', '');
            if (strlen($password) < 8) {
                Http::flash('Password must be at least 8 characters.', 'error');
                Http::redirect('/admin/users');
            }
            Db::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => $id]);
        } else {
            Db::update('users', [
                'display_name' => (string) Http::input('display_name', (string) $user['display_name']),
                'role' => in_array(Http::input('role'), Auth::roles(), true) ? (string) Http::input('role') : (string) $user['role'],
            ], ['id' => $id]);
        }

        Http::flash('User updated.');
        Http::redirect('/admin/users');
    }

    private function adHocShare(int $siteId): int
    {
        $total = (int) Db::value('SELECT COUNT(*) FROM entries WHERE site_id = ?', [$siteId]);
        if ($total === 0) {
            return 0;
        }

        $adHoc = (int) Db::value('SELECT COUNT(*) FROM entries WHERE site_id = ? AND location_id IS NULL', [$siteId]);

        return (int) round($adHoc / $total * 100);
    }
}
