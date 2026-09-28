<?php

namespace Tests\Unit;

use App\Support\MapPackagePublisher;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class MapPackagePublisherTest extends TestCase
{
    private string $runtime;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runtime = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hwrt-map-'.Str::uuid();
        mkdir($this->runtime.DIRECTORY_SEPARATOR.'versions'.DIRECTORY_SEPARATOR.'old', 0777, true);
        mkdir($this->runtime.DIRECTORY_SEPARATOR.'versions'.DIRECTORY_SEPARATOR.'new', 0777, true);
        file_put_contents($this->runtime.DIRECTORY_SEPARATOR.'versions'.DIRECTORY_SEPARATOR.'old'.DIRECTORY_SEPARATOR.'manifest.json', '{"version":"old"}');
        file_put_contents($this->runtime.DIRECTORY_SEPARATOR.'versions'.DIRECTORY_SEPARATOR.'new'.DIRECTORY_SEPARATOR.'manifest.json', '{"version":"new"}');
        symlink('versions/old', $this->runtime.DIRECTORY_SEPARATOR.'current');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->runtime);
        parent::tearDown();
    }

    public function test_publish_switches_current_pointer_and_can_restore_previous_package(): void
    {
        $publisher = new MapPackagePublisher;
        $current = $this->runtime.DIRECTORY_SEPARATOR.'current';
        $oldTarget = $publisher->publish(
            $current,
            $this->runtime.DIRECTORY_SEPARATOR.'versions'.DIRECTORY_SEPARATOR.'new',
        );

        $this->assertSame('versions/new', readlink($current));
        $this->assertSame('{"version":"new"}', file_get_contents($current.DIRECTORY_SEPARATOR.'manifest.json'));

        $publisher->restore($current, $oldTarget);

        $this->assertSame('versions/old', readlink($current));
        $this->assertSame('{"version":"old"}', file_get_contents($current.DIRECTORY_SEPARATOR.'manifest.json'));
    }

    public function test_invalid_candidate_does_not_change_current_pointer(): void
    {
        $publisher = new MapPackagePublisher;
        $current = $this->runtime.DIRECTORY_SEPARATOR.'current';

        try {
            $publisher->publish($current, $this->runtime.DIRECTORY_SEPARATOR.'missing');
            $this->fail('Expected an invalid candidate to be rejected.');
        } catch (RuntimeException) {
            $this->assertSame('versions/old', readlink($current));
        }
    }
}
