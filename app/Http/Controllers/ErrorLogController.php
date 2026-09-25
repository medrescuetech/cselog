<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ErrorLogController extends Controller
{
    private function path(): string
    {
        return storage_path('logs/csem-errors.log');
    }

    public function index(Request $request)
    {
        $lines = max(50, min(1000, (int) $request->query('lines', 300)));
        $path = $this->path();
        $exists = is_file($path);
        $content = $exists ? $this->tail($path, $lines) : '';
        $size = $exists ? filesize($path) : 0;
        $modified = $exists ? filemtime($path) : null;

        return view('errors.log', compact('content', 'lines', 'size', 'modified', 'exists'));
    }

    public function download()
    {
        $path = $this->path();
        abort_unless(is_file($path), 404, 'Error log has not been created yet.');

        return response()->download($path, 'csem-errors.log', [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function clear(Request $request)
    {
        $path = $this->path();
        if (is_file($path)) {
            file_put_contents($path, '');
        }

        Log::channel('csem_errors')->warning('Error log cleared by administrator.', [
            'user_id' => $request->user()?->id,
            'email' => $request->user()?->email,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('errors.index')->with('status', 'Error log cleared.');
    }

    public function client(Request $request)
    {
        $data = $request->validate([
            'kind' => 'nullable|string|max:80',
            'message' => 'required|string|max:4000',
            'stack' => 'nullable|string|max:12000',
            'url' => 'nullable|string|max:2000',
            'source' => 'nullable|string|max:2000',
            'line' => 'nullable|integer|min:0',
            'column' => 'nullable|integer|min:0',
            'context' => 'nullable|array',
        ]);

        $context = is_array($data['context'] ?? null) ? $data['context'] : [];

        Log::channel('csem_errors')->warning('Browser error: '.$data['message'], [
            'event_id' => (string) Str::uuid(),
            'kind' => $data['kind'] ?? 'browser',
            'url' => $data['url'] ?? $request->headers->get('referer'),
            'source' => $data['source'] ?? null,
            'line' => $data['line'] ?? null,
            'column' => $data['column'] ?? null,
            'stack' => $data['stack'] ?? null,
            'context' => $context,
            'user_id' => $request->user()?->id,
            'email' => $request->user()?->email,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500),
        ]);

        return response()->json(['ok' => true], 202);
    }

    private function tail(string $path, int $lineCount): string
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            return '';
        }

        $buffer = '';
        $position = filesize($path);
        $chunkSize = 8192;

        while ($position > 0 && substr_count($buffer, "\n") <= $lineCount) {
            $read = min($chunkSize, $position);
            $position -= $read;
            fseek($handle, $position);
            $buffer = fread($handle, $read).$buffer;

            // Keep this page bounded even if a stack trace contains huge lines.
            if (strlen($buffer) > 1024 * 1024) {
                break;
            }
        }

        fclose($handle);

        $rows = preg_split('/\R/', trim($buffer)) ?: [];

        return implode("\n", array_slice($rows, -$lineCount));
    }
}
