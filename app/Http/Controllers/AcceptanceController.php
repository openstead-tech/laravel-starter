<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class AcceptanceController extends Controller
{
    public function index(): View
    {
        return view('welcome');
    }

    public function health(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
            DB::table('acceptance_records')->limit(1)->count();
            return response()->json(['ok' => true, 'framework' => 'laravel']);
        } catch (Throwable) {
            return response()->json(['ok' => false], 503);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'marker' => ['required', 'string', 'max:120'],
            'file' => ['required', 'file', 'mimetypes:text/plain', 'max:64'],
        ]);
        $id = bin2hex(random_bytes(16));
        $path = 'acceptance/'.$id.'.txt';
        // Customer filenames never determine storage paths and files remain private.
        Storage::disk('local')->put($path, $request->file('file')->getContent());
        try {
            DB::table('acceptance_records')->insert([
                'id' => $id, 'marker' => $data['marker'], 'upload_path' => $path,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
        return $this->record($id, 201);
    }

    public function show(string $id): JsonResponse
    {
        return $this->record($id);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['marker' => ['required', 'string', 'max:120']]);
        abort_unless(DB::table('acceptance_records')->where('id', $id)->exists(), 404);
        DB::table('acceptance_records')->where('id', $id)->update(['marker' => $data['marker'], 'updated_at' => now()]);
        return $this->record($id);
    }

    private function record(string $id, int $status = 200): JsonResponse
    {
        $record = DB::table('acceptance_records')->where('id', $id)->first();
        abort_unless($record, 404);
        $exists = Storage::disk('local')->exists($record->upload_path);
        return response()->json([
            'id' => $record->id, 'marker' => $record->marker,
            'filePresent' => $exists,
            'fileSha256' => $exists ? hash('sha256', Storage::disk('local')->get($record->upload_path)) : null,
        ], $status);
    }

    public function destroy(string $id): JsonResponse
    {
        $record = DB::table('acceptance_records')->where('id', $id)->first();
        abort_unless($record, 404);
        Storage::disk('local')->delete($record->upload_path);
        DB::table('acceptance_records')->where('id', $id)->delete();
        return response()->json(['deleted' => true]);
    }
}
