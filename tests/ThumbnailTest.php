<?php

use Encore\Admin\Auth\Database\Administrator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\Models\Image;

class ThumbnailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->be(Administrator::first(), 'admin');

        File::cleanDirectory(public_path('uploads'));
    }

    protected function uploadImage()
    {
        $copy = tempnam(sys_get_temp_dir(), 'thumbnail');

        copy(__DIR__.'/assets/test.jpg', $copy);

        $this->call('POST', 'admin/thumbnails', [], [], [
            'image1' => new UploadedFile($copy, 'test.jpg', 'image/jpeg', null, true),
        ]);

        @unlink($copy);
    }

    public function testThumbnailsAreCreated()
    {
        $this->uploadImage();

        $this->assertEquals(1, Image::count());
        $this->assertEquals('images/test.jpg', Image::first()->image1);

        $this->assertFileExists(public_path('uploads/images/test.jpg'));
        $this->assertFileExists(public_path('uploads/images/test-small.jpg'));
        $this->assertFileExists(public_path('uploads/images/test-cover.jpg'));
    }

    public function testThumbnailsKeepTheRequestedSize()
    {
        $this->uploadImage();

        // The original is 200x300, the thumbnail canvas is always the requested size.
        $this->assertEquals([100, 100], array_slice(getimagesize(public_path('uploads/images/test-small.jpg')), 0, 2));
        $this->assertEquals([60, 60], array_slice(getimagesize(public_path('uploads/images/test-cover.jpg')), 0, 2));
    }

    public function testThumbnailsAreRemovedWithTheRecord()
    {
        $this->uploadImage();

        $this->delete('admin/thumbnails/1');

        $this->assertEquals(0, Image::count());
        $this->assertFileDoesNotExist(public_path('uploads/images/test.jpg'));
        $this->assertFileDoesNotExist(public_path('uploads/images/test-small.jpg'));
        $this->assertFileDoesNotExist(public_path('uploads/images/test-cover.jpg'));
    }
}
