<?php

use App\Services\S3UrlPresigner;
use Carbon\Carbon;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['filesystems.disks.s3.bucket' => 'arquivos']);
    $this->travelTo(Carbon::parse('2026-06-15 10:00:00'));
});

afterEach(fn () => $this->travelBack());

test('assina a chave correta por cinco minutos sem enviar a URL inteira ao armazenamento', function (string $url, string $key) {
    $disk = Mockery::mock(FilesystemAdapter::class);
    Storage::shouldReceive('disk')->once()->with('s3')->andReturn($disk);
    $disk->shouldReceive('temporaryUrl')->once()->with(
        $key,
        Mockery::on(fn ($expiration) => $expiration instanceof Carbon
            && $expiration->equalTo(Carbon::parse('2026-06-15 10:05:00'))),
        []
    )->andReturn('https://assinado.test/arquivo?assinatura=temporaria');

    expect((new S3UrlPresigner)->getPresignedUrl($url))
        ->toBe('https://assinado.test/arquivo?assinatura=temporaria');
})->with([
    'bucket no caminho' => ['https://s3.test/arquivos/escola/boletim.pdf', 'escola/boletim.pdf'],
    'bucket no host' => ['https://arquivos.s3.test/escola/boletim.pdf', 'escola/boletim.pdf'],
    'prefixo parecido com o bucket' => ['https://arquivos.s3.test/arquivos-antigos/boletim.pdf', 'arquivos-antigos/boletim.pdf'],
    'nome codificado duas vezes' => ['https://s3.test/arquivos/escola/laudo%2520m%25C3%25A9dico.pdf', 'escola/laudo médico.pdf'],
    'consulta e fragmento fora da chave' => ['https://arquivos.s3.test/boletim.pdf?download=1#pagina-2', 'boletim.pdf'],
]);

test('codifica o nome de exibição sem permitir aspas ou quebras de linha no cabeçalho', function () {
    $disk = Mockery::mock(FilesystemAdapter::class);
    Storage::shouldReceive('disk')->once()->with('s3')->andReturn($disk);
    $disk->shouldReceive('temporaryUrl')->once()->with(
        'boletim.pdf',
        Mockery::type(Carbon::class),
        ['ResponseContentDisposition' => 'inline; filename="boletim%20%22Jo%C3%A3o%22%0D%0A.pdf"']
    )->andReturn('https://assinado.test/boletim.pdf');

    expect((new S3UrlPresigner)->getPresignedUrl('https://arquivos.s3.test/boletim.pdf', "boletim \"João\"\r\n.pdf"))
        ->toBe('https://assinado.test/boletim.pdf');
});

test('não acessa o armazenamento quando a URL não identifica um arquivo', function (string $url) {
    Storage::shouldReceive('disk')->never();

    expect((new S3UrlPresigner)->getPresignedUrl($url))->toBe('');
})->with(['', 'https://arquivos.s3.test', 'https://arquivos.s3.test/', 'https://s3.test/arquivos/']);

test('assina o caminho inteiro quando não há bucket configurado', function () {
    config(['filesystems.disks.s3.bucket' => null]);
    $disk = Mockery::mock(FilesystemAdapter::class);
    Storage::shouldReceive('disk')->once()->with('s3')->andReturn($disk);
    $disk->shouldReceive('temporaryUrl')->once()->with('arquivos/boletim.pdf', Mockery::type(Carbon::class), [])
        ->andReturn('https://assinado.test/boletim.pdf');

    expect((new S3UrlPresigner)->getPresignedUrl('https://s3.test/arquivos/boletim.pdf'))
        ->toBe('https://assinado.test/boletim.pdf');
});

test('propaga a falha do armazenamento sem devolver uma URL como se estivesse assinada', function () {
    Storage::shouldReceive('disk')->once()->with('s3')->andThrow(new RuntimeException('Falha ao acessar o armazenamento.'));

    expect(fn () => (new S3UrlPresigner)->getPresignedUrl('https://arquivos.s3.test/boletim.pdf'))
        ->toThrow(RuntimeException::class, 'Falha ao acessar o armazenamento.');
});
