<?php

use Encore\Admin\Auth\Database\Administrator;
use Illuminate\Support\Facades\File;
use Tests\Models\File as FileModel;

class FileUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->be(Administrator::first(), 'admin');
    }

    public function testFileUploadPage()
    {
        $this->visit('admin/files/create')
            ->see('Files')
            ->seeInElement('h3[class=box-title]', 'Create')
            ->seeElement('input[name=file1]')
            ->seeElement('input[name=file2]')
            ->seeElement('input[name=file3]')
            ->seeElement('input[name=file4]')
            ->seeElement('input[name=file5]')
            ->seeElement('input[name=file6]')
//            ->seeInElement('a[href="/admin/files"]', 'List')
            ->seeInElement('button[type=reset]', 'Reset')
            ->seeInElement('button[type=submit]', 'Submit');
    }

    protected function uploadFiles()
    {
        return $this->visit('admin/files/create')
            ->attach(__DIR__.'/assets/document-1.txt', 'file1')
            ->attach(__DIR__.'/assets/document-2.txt', 'file2')
            ->attach(__DIR__.'/assets/document-3.txt', 'file3')
            ->attach(__DIR__.'/assets/document-4.txt', 'file4')
            ->attach(__DIR__.'/assets/document-5.txt', 'file5')
            ->attach(__DIR__.'/assets/document-6.txt', 'file6')
            ->press('Submit');
    }

    public function testUploadFile()
    {
        File::cleanDirectory(public_path('uploads/files'));

        $this->uploadFiles()
            ->seePageIs('admin/files');

        $this->assertEquals(FileModel::count(), 1);

        $where = [
            'file1' => 'files/document-1.txt',
            'file2' => 'files/document-2.txt',
            'file3' => 'files/document-3.txt',
            'file4' => 'files/document-4.txt',
            'file5' => 'files/document-5.txt',
            'file6' => 'files/document-6.txt',
        ];

        $this->seeInDatabase('test_files', $where);

        $files = FileModel::first()->toArray();

        foreach (range(1, 6) as $index) {
            $this->assertFileExists(public_path('uploads/'.$files['file'.$index]));
        }

        File::cleanDirectory(public_path('uploads/files'));
    }

    public function testUpdateFile()
    {
        File::cleanDirectory(public_path('uploads/files'));

        $this->uploadFiles();

        $old = FileModel::first();

        $this->visit('admin/files/1/edit')
            ->see('ID')
            ->see('Created At')
            ->see('Updated At')
            ->seeElement('input[name=file1]')
            ->seeElement('input[name=file2]')
            ->seeElement('input[name=file3]')
            ->seeElement('input[name=file4]')
            ->seeElement('input[name=file5]')
            ->seeElement('input[name=file6]')
//            ->seeInElement('a[href="/admin/files"]', 'List')
            ->seeInElement('button[type=reset]', 'Reset')
            ->seeInElement('button[type=submit]', 'Submit');

        $this->attach(__DIR__.'/assets/document-7.txt', 'file3')
            ->attach(__DIR__.'/assets/document-8.txt', 'file4')
            ->attach(__DIR__.'/assets/document-9.txt', 'file5')
            ->press('Submit');

        $new = FileModel::first();

        $this->assertEquals($old->id, $new->id);
        $this->assertEquals($old->file1, $new->file1);
        $this->assertEquals($old->file2, $new->file2);
        $this->assertEquals($old->file6, $new->file6);

        $this->assertNotEquals($old->file3, $new->file3);
        $this->assertNotEquals($old->file4, $new->file4);
        $this->assertNotEquals($old->file5, $new->file5);

        File::cleanDirectory(public_path('uploads/files'));
    }

    public function testDeleteFiles()
    {
        File::cleanDirectory(public_path('uploads/files'));

        $this->uploadFiles();

        $this->visit('admin/files')
            ->seeInElement('td', 1);

        $files = FileModel::first()->toArray();

        $this->delete('admin/files/1')
            ->dontSeeInDatabase('test_files', ['id' => 1]);

        foreach (range(1, 6) as $index) {
            $this->assertFileDoesNotExist(public_path('uploads/'.$files['file'.$index]));
        }

        $this->visit('admin/files')
            ->seeInElement('td', 'svg');
    }

    public function testBatchDelete()
    {
        File::cleanDirectory(public_path('uploads/files'));

        $this->uploadFiles();
        $this->uploadFiles();
        $this->uploadFiles();

        $this->visit('admin/files')
            ->seeInElement('td', 1)
            ->seeInElement('td', 2)
            ->seeInElement('td', 3);

        $fi = new FilesystemIterator(public_path('uploads/files'), FilesystemIterator::SKIP_DOTS);

        $this->assertEquals(iterator_count($fi), 18);

        $this->assertEquals(FileModel::count(), 3);

        $this->delete('admin/files/1,2,3');

        $this->assertEquals(FileModel::count(), 0);

        $this->visit('admin/files')
            ->seeInElement('td', 'svg');

        $this->assertEquals(iterator_count($fi), 0);
    }

    public function testScriptsCanNotBeUploaded()
    {
        File::cleanDirectory(public_path('uploads/files'));

        foreach (['shell.php', 'shell.PHP', 'shell.phtml', 'shell.php.jpg', 'shell.jpg.php', '.htaccess'] as $name) {
            $copy = sys_get_temp_dir().'/'.uniqid().'-'.$name;
            file_put_contents($copy, "<?php echo 'x';");

            $this->call('POST', '/admin/files', [], [], [
                'file1' => new \Illuminate\Http\UploadedFile($copy, $name, 'text/plain', null, true),
            ]);

            @unlink($copy);

            $this->assertEquals(0, FileModel::count(), "{$name} was stored.");
            $directory = public_path('uploads/files');
            $written = is_dir($directory) ? array_diff(scandir($directory), ['.', '..']) : [];

            $this->assertSame([], array_values($written), "{$name} was written to the disk.");
        }
    }

    public function testDocumentsCanBeUploaded()
    {
        File::cleanDirectory(public_path('uploads/files'));

        $this->uploadFiles()->seePageIs('admin/files');

        $this->assertEquals(1, FileModel::count());
    }
}
