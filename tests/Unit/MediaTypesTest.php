<?php

namespace Tests\Unit;

use App\Support\MediaTypes;
use PHPUnit\Framework\TestCase;

class MediaTypesTest extends TestCase
{
    public function test_it_returns_only_image_types_for_images(): void
    {
        $types = MediaTypes::for('image');

        $this->assertContains('image/jpeg', $types);
        $this->assertNotContains('video/mp4', $types);
    }

    public function test_it_returns_only_video_types_for_videos(): void
    {
        $types = MediaTypes::for('video');

        $this->assertContains('video/mp4', $types);
        $this->assertNotContains('image/jpeg', $types);
    }

    public function test_it_allows_both_when_no_type_is_chosen(): void
    {
        $types = MediaTypes::for(null);

        $this->assertContains('image/jpeg', $types);
        $this->assertContains('video/mp4', $types);
    }

    public function test_every_type_is_a_mime_type(): void
    {
        foreach (MediaTypes::for(null) as $type) {
            $this->assertMatchesRegularExpression('#^(image|video)/[a-z0-9.+-]+$#', $type);
        }
    }

    public function test_it_detects_the_type_from_the_file_path(): void
    {
        $this->assertSame('video', MediaTypes::typeForPath('products/media/clip.MP4'));
        $this->assertSame('video', MediaTypes::typeForPath('products/media/clip.mov'));
        $this->assertSame('image', MediaTypes::typeForPath('products/media/detail.jpg'));
        $this->assertSame('image', MediaTypes::typeForPath('products/media/no-extension'));
        $this->assertSame('image', MediaTypes::typeForPath(null));
    }
}
