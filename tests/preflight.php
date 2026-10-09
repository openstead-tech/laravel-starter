<?php

// An actual Laravel kernel preflight using a disposable in-memory SQLite DB.
// This does not replace MySQL/container/replacement/backup acceptance.
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

$finished = false;
register_shutdown_function(function () use (&$finished): void {
    if (!$finished) exit(1);
});
$token = bin2hex(random_bytes(32));
foreach (['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
          'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'ACCEPTANCE_TOKEN' => $token] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$temporary = sys_get_temp_dir().'/runivo-laravel-'.bin2hex(random_bytes(12));
foreach (['', '/app/private', '/framework/views', '/framework/cache', '/logs'] as $directory) {
    if (!is_dir($temporary.$directory)) mkdir($temporary.$directory, 0700, true);
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->useStoragePath($temporary);
$app->make(ConsoleKernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
    throw new RuntimeException('Refusing to test against a persistent database.');
}
$kernel = $app->make(HttpKernel::class);
$checks = 0;
$expect = function (bool $condition, string $description) use (&$checks): void {
    if (!$condition) throw new RuntimeException($description);
    $checks++;
};
$send = function (string $uri, string $method = 'GET', array $parameters = [], array $files = [], string $credential = '') use ($kernel) {
    $request = Request::create($uri, $method, $parameters, [], $files,
        ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_RUNIVO_ACCEPTANCE' => $credential]);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    return $response;
};

$expect($send('/health')->getStatusCode() === 503, 'Health should fail before migrations.');
$expect(Artisan::call('migrate', ['--force' => true]) === 0, 'Migration failed.');
$expect(Artisan::call('migrate', ['--force' => true]) === 0, 'Repeated migration should be safe.');
$expect($send('/health')->getStatusCode() === 200, 'Health should pass with a migrated database.');
$expect($send('/acceptance/records', 'POST')->getStatusCode() === 401, 'Write must require a token.');
$expect($send('/acceptance/records', 'POST', [], [], str_repeat('x', 64))->getStatusCode() === 401, 'Wrong token must be denied.');
$expect($send('/acceptance/records', 'POST', [], [], $token)->getStatusCode() === 422, 'Missing input must fail validation.');

$content = "Persistent Laravel upload acceptance\n";
$uploadPath = $temporary.'/upload.txt';
file_put_contents($uploadPath, $content);
$rejected = new UploadedFile($uploadPath, 'private.php', 'text/plain', null, true);
$expect($send('/acceptance/records', 'POST', ['marker' => 'rejected'], ['file' => $rejected], $token)->getStatusCode() === 422,
        'PHP filenames must be rejected even when the contents are plain text.');
$upload = new UploadedFile($uploadPath, '../../private.txt', 'text/plain', null, true);
$created = $send('/acceptance/records', 'POST', ['marker' => "literal ' marker"], ['file' => $upload], $token);
$expect($created->getStatusCode() === 201, 'Authenticated upload failed: '.$created->getContent());
$record = json_decode($created->getContent(), true, flags: JSON_THROW_ON_ERROR);
$expect($record['filePresent'] && $record['fileSha256'] === hash('sha256', $content), 'Upload digest mismatch.');
$expect($record['marker'] === "literal ' marker", 'Marker should be stored literally.');
$expect(preg_match('/^[a-f0-9]{32}$/', $record['id']) === 1, 'Record ID is not a generated safe identifier.');
$stored = DB::table('acceptance_records')->where('id', $record['id'])->first();
$expect($stored->upload_path === 'acceptance/'.$record['id'].'.txt', 'Client filename affected upload path.');
$expect(!str_contains($created->getContent(), $stored->upload_path), 'Private storage path leaked.');
$expect($send('/acceptance/records/'.$record['id'])->getStatusCode() === 401, 'Read must require a token.');
$read = $send('/acceptance/records/'.$record['id'], credential: $token);
$expect(json_decode($read->getContent(), true)['fileSha256'] === $record['fileSha256'], 'Read did not preserve the upload.');
$expect($send('/acceptance/records/'.$record['id'], 'PATCH', ['marker' => 'forbidden'])->getStatusCode() === 401,
        'Update must require a token.');
$updated = $send('/acceptance/records/'.$record['id'], 'PATCH', ['marker' => 'after-backup'], credential: $token);
$expect($updated->getStatusCode() === 200 && json_decode($updated->getContent(), true)['marker'] === 'after-backup',
        'Authenticated update did not persist.');
$expect(json_decode($updated->getContent(), true)['fileSha256'] === $record['fileSha256'], 'Marker update changed the upload.');
$expect($send('/storage/acceptance/'.$record['id'].'.txt')->getStatusCode() === 404, 'Upload was publicly accessible.');
config(['acceptance.token' => '']);
$expect($send('/acceptance/records/'.$record['id'], credential: $token)->getStatusCode() === 401, 'Cleared token must disable acceptance endpoints.');
config(['acceptance.token' => $token]);
$expect($send('/acceptance/records/'.$record['id'], 'DELETE', credential: $token)->getStatusCode() === 200, 'Delete failed.');
$expect(!Storage::disk('local')->exists($stored->upload_path), 'Delete did not remove the upload.');
$expect($send('/acceptance/records/'.$record['id'], credential: $token)->getStatusCode() === 404, 'Deleted record still exists.');
$expect(DB::table('acceptance_records')->count() === 0, 'Preflight left records behind.');

$index = $send('/');
$expect($index->getStatusCode() === 200 && str_contains($index->getContent(), '/build/assets/'), 'Blade/Vite page did not render.');
unlink($uploadPath);
$finished = true;
echo "PASS: {$checks} Laravel kernel checks (SQLite preflight only).\n";
