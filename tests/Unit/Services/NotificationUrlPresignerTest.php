<?php

use App\Models\Notification;
use App\Models\NotificationType;
use App\Services\NotificationUrlPresigner;
use Carbon\Carbon;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

test('assina o arquivo das notificações de exportação de alunos e servidores', function (int $type) {
    config(['filesystems.default' => 's3', 'filesystems.disks.s3.bucket' => 'arquivos']);
    $notification = new Notification(['type_id' => $type, 'link' => 'https://arquivos.s3.test/exportacoes/arquivo.csv']);
    $disk = Mockery::mock(FilesystemAdapter::class);
    Storage::shouldReceive('disk')->once()->with('s3')->andReturn($disk);
    $disk->shouldReceive('temporaryUrl')->once()->with('exportacoes/arquivo.csv', Mockery::type(Carbon::class), [])
        ->andReturn('https://assinado.test/arquivo.csv?assinatura=temporaria');

    expect((new NotificationUrlPresigner)->getNotificationUrl($notification))
        ->toBe('https://assinado.test/arquivo.csv?assinatura=temporaria')
        ->and($notification->link)->toBe('https://arquivos.s3.test/exportacoes/arquivo.csv');
})->with([NotificationType::EXPORT_STUDENT, NotificationType::EXPORT_TEACHER]);

test('preserva os links das demais notificações sem acessar o armazenamento', function (?int $type) {
    config(['filesystems.default' => 's3']);
    Storage::shouldReceive('disk')->never();
    $notification = new Notification(['type_id' => $type, 'link' => '/intranet/educar_index.php?origem=aviso#pendencias']);

    expect((new NotificationUrlPresigner)->getNotificationUrl($notification))
        ->toBe('/intranet/educar_index.php?origem=aviso#pendencias');
})->with([NotificationType::TRANSFER, NotificationType::OTHER, NotificationType::ACTIVE_LOOKING, 999, null]);

test('preserva a URL de exportação quando o disco é local', function () {
    config(['filesystems.default' => 'local']);
    Storage::shouldReceive('disk')->never();
    $notification = new Notification(['type_id' => NotificationType::EXPORT_STUDENT, 'link' => '/storage/alunos.csv']);

    expect((new NotificationUrlPresigner)->getNotificationUrl($notification))->toBe('/storage/alunos.csv');
});

test('mantém vazio o link de exportação sem arquivo e não tenta assiná-lo', function () {
    config(['filesystems.default' => 's3']);
    Storage::shouldReceive('disk')->never();
    $notification = new Notification(['type_id' => NotificationType::EXPORT_TEACHER, 'link' => '']);

    expect((new NotificationUrlPresigner)->getNotificationUrl($notification))->toBe('');
});

test('explica a falta de suporte ao disco de uma exportação em vez de expor o link original', function () {
    config(['filesystems.default' => 'ftp']);
    Storage::shouldReceive('disk')->never();
    $notification = new Notification(['type_id' => NotificationType::EXPORT_STUDENT, 'link' => 'https://arquivos.test/alunos.csv']);

    expect(fn () => (new NotificationUrlPresigner)->getNotificationUrl($notification))
        ->toThrow(Exception::class, 'Method UrlPresigner::getPresignedUrl() not implemented for cloud filesystem: ftp');
});
