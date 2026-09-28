<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LocationImportController extends Controller
{
    public function index()
    {
        return view('settings.locations.import', ['rows' => null, 'token' => null, 'errorsFound' => false]);
    }

    public function preview(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:1024']);
        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'CSV is empty.']);
        }
        $names = array_map(fn ($name) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $name))), $header);
        $required = ['name', 'code', 'easting', 'northing', 'epsg', 'aliases'];
        if (array_diff($required, $names) || count(array_unique($names)) !== count($names)) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'CSV must have unique headers: name,code,easting,northing,epsg,aliases.']);
        }
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (count($values) === 1 && trim($values[0]) === '') continue;
            if (count($rows) >= 500) {
                fclose($handle);
                throw ValidationException::withMessages(['file' => 'Import is limited to 500 locations per CSV.']);
            }
            $rows[] = count($values) === count($names) ? array_combine($names, $values) : ['invalid' => true];
        }
        fclose($handle);
        if (! $rows) {
            throw ValidationException::withMessages(['file' => 'No location rows found.']);
        }
        $rows = $this->assess($rows);
        $oldToken = $request->session()->pull('location_import_token');
        if ($oldToken) Storage::disk('local')->delete("location-imports/{$oldToken}.json");
        $token = (string) Str::uuid();
        Storage::disk('local')->put("location-imports/{$token}.json", json_encode($rows));
        $request->session()->put('location_import_token', $token);

        return view('settings.locations.import', [
            'rows' => $rows, 'token' => $token, 'errorsFound' => collect($rows)->contains(fn ($row) => $row['error'] !== null),
        ]);
    }

    public function apply(Request $request)
    {
        $data = $request->validate(['token' => 'required|uuid']);
        if ($request->session()->get('location_import_token') !== $data['token']) abort(403);
        $path = "location-imports/{$data['token']}.json";
        if (! Storage::disk('local')->exists($path)) abort(410);
        $rows = json_decode(Storage::disk('local')->get($path), true);
        if (! is_array($rows) || ! $rows) abort(422);
        $rows = $this->assess($rows);
        if (collect($rows)->contains(fn ($row) => $row['error'] !== null)) {
            throw ValidationException::withMessages(['file' => 'Catalogue changed or CSV has conflicts. Upload the CSV again to preview.']);
        }

        DB::transaction(function () use ($rows, $request) {
            foreach ($rows as $row) {
                $location = Location::create([
                    'name' => $row['name'], 'code' => $row['code'] ?: null,
                    'aliases' => $row['aliases'] === '' ? [] : array_values(array_filter(array_map('trim', explode('|', $row['aliases'])))),
                    'easting' => $row['easting'], 'northing' => $row['northing'],
                    'area_id' => Area::containing((float) $row['easting'], (float) $row['northing'])?->id,
                    'verified' => true, 'created_by' => $request->user()->id,
                ]);
                $location->recordRevision('imported', null, 'Admin-reviewed MGA50 CSV');
            }
        });
        Storage::disk('local')->delete($path);
        $request->session()->forget('location_import_token');

        return redirect()->route('settings.locations.index')->with('status', count($rows).' locations imported.');
    }

    private function assess(array $rows): array
    {
        $seenNames = [];
        $seenCodes = [];
        $seenPins = [];
        foreach ($rows as &$row) {
            $row = array_intersect_key($row, array_flip(['name', 'code', 'easting', 'northing', 'epsg', 'aliases']))
                + ['name' => '', 'code' => '', 'easting' => '', 'northing' => '', 'epsg' => '', 'aliases' => ''];
            foreach ($row as &$value) $value = is_string($value) ? trim($value) : '';
            unset($value);
            $row['error'] = null;
            $name = strtolower($row['name']);
            $code = strtolower($row['code']);
            $e = $row['easting']; $n = $row['northing'];
            if ($name === '' || mb_strlen($row['name']) > 160 || mb_strlen($row['code']) > 60 || mb_strlen($row['aliases']) > 500) {
                $row['error'] = 'Name required, or name/code/aliases too long.';
            } elseif ($row['epsg'] !== '28350' || ! is_numeric($e) || ! is_numeric($n)
                || (float) $e < 0 || (float) $e > 999999.999 || (float) $n < 0 || (float) $n > 99999999.999) {
                $row['error'] = 'Expected numeric MGA Zone 50 coordinates and epsg 28350.';
            } elseif (isset($seenNames[$name]) || ($code !== '' && isset($seenCodes[$code]))
                || Location::whereRaw('LOWER(name) = ?', [$name])->exists()
                || ($code !== '' && Location::whereRaw('LOWER(code) = ?', [$code])->exists())) {
                $row['error'] = 'Name or code already exists in catalogue or CSV.';
            } elseif (Location::near((float) $e, (float) $n, 15)->isNotEmpty()
                || collect($seenPins)->contains(fn ($pin) => hypot($pin[0] - (float) $e, $pin[1] - (float) $n) <= 15)) {
                $row['error'] = 'A saved location is within 15 m; review manually.';
            }
            $seenNames[$name] = true;
            if ($code !== '') $seenCodes[$code] = true;
            if (is_numeric($e) && is_numeric($n)) $seenPins[] = [(float) $e, (float) $n];
        }
        unset($row);

        return $rows;
    }
}
