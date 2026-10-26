<?php

namespace Encore\Admin\Form\Field;

use Illuminate\Support\Str;
use Intervention\Image\Alignment;
use Intervention\Image\Direction;
use Intervention\Image\Laravel\Facades\Image as InterventionImage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

trait ImageField
{
    /**
     * Intervention calls.
     *
     * @var array
     */
    protected $interventionCalls = [];

    /**
     * Thumbnail settings.
     *
     * @var array
     */
    protected $thumbnails = [];

    /**
     * Thumbnail actions of intervention/image 2.x mapped to their 4.x counterpart.
     *
     * @var array
     */
    protected $thumbnailActions = [
        'resize' => 'scale',
        'fit'    => 'cover',
    ];

    /**
     * Default directory for file to upload.
     *
     * @return mixed
     */
    public function defaultDirectory()
    {
        return config('admin.upload.directory.image');
    }

    /**
     * Execute Intervention calls.
     *
     * @param string $target
     *
     * @return mixed
     */
    public function callInterventionMethods($target)
    {
        if (!empty($this->interventionCalls)) {
            $image = InterventionImage::decodePath($target);

            foreach ($this->interventionCalls as $call) {
                call_user_func_array(
                    [$image, $call['method']],
                    $this->prepareInterventionArguments($call['method'], $call['arguments'])
                )->save($target);
            }
        }

        return $target;
    }

    /**
     * Convert arguments written for intervention/image 2.x to the 4.x signature.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return array
     */
    protected function prepareInterventionArguments($method, array $arguments)
    {
        if ($method === 'flip' && isset($arguments[0]) && is_string($arguments[0])) {
            $arguments[0] = in_array($arguments[0], ['v', 'vertical'], true)
                ? Direction::VERTICAL
                : Direction::HORIZONTAL;
        }

        return $arguments;
    }

    /**
     * Call intervention methods.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function __call($method, $arguments)
    {
        if (static::hasMacro($method)) {
            return $this;
        }

        if (!class_exists(InterventionImage::class)) {
            throw new \Exception('To use image handling and manipulation, please install [intervention/image-laravel] first.');
        }

        $this->interventionCalls[] = [
            'method'    => $method,
            'arguments' => $arguments,
        ];

        return $this;
    }

    /**
     * Render a image form field.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function render()
    {
        $this->options(['allowedFileTypes' => ['image'], 'msgPlaceholder' => trans('admin.choose_image')]);

        return parent::render();
    }

    /**
     * @param string|array $name
     * @param int          $width
     * @param int          $height
     *
     * @return $this
     */
    public function thumbnail($name, ?int $width = null, ?int $height = null)
    {
        if (func_num_args() == 1 && is_array($name)) {
            foreach ($name as $key => $size) {
                if (count($size) >= 2) {
                    $this->thumbnails[$key] = $size;
                }
            }
        } elseif (func_num_args() == 3) {
            $this->thumbnails[$name] = [$width, $height];
        }

        return $this;
    }

    /**
     * Destroy original thumbnail files.
     *
     * @return void.
     */
    public function destroyThumbnail()
    {
        if ($this->retainable) {
            return;
        }

        foreach ($this->thumbnails as $name => $_) {
            /*  Refactoring actual remove lofic to another method destroyThumbnailFile()
            to make deleting thumbnails work with multiple as well as
            single image upload. */

            if (is_array($this->original)) {
                if (empty($this->original)) {
                    continue;
                }

                foreach ($this->original as $original) {
                    $this->destroyThumbnailFile($original, $name);
                }
            } else {
                $this->destroyThumbnailFile($this->original, $name);
            }
        }
    }

    /**
     * Remove thumbnail file from disk.
     *
     * @return void.
     */
    public function destroyThumbnailFile($original, $name)
    {
        $ext = @pathinfo($original, PATHINFO_EXTENSION);

        // We remove extension from file name so we can append thumbnail type
        $path = @Str::replaceLast('.'.$ext, '', $original);

        // We merge original name + thumbnail name + extension
        $path = $path.'-'.$name.'.'.$ext;

        if ($this->storage->exists($path)) {
            $this->storage->delete($path);
        }
    }

    /**
     * Upload file and delete original thumbnail files.
     *
     * @param UploadedFile $file
     *
     * @return $this
     */
    protected function uploadAndDeleteOriginalThumbnail(UploadedFile $file)
    {
        foreach ($this->thumbnails as $name => $size) {
            // We need to get extension type ( .jpeg , .png ...)
            $ext = pathinfo($this->name, PATHINFO_EXTENSION);

            // We remove extension from file name so we can append thumbnail type
            $path = Str::replaceLast('.'.$ext, '', $this->name);

            // We merge original name + thumbnail name + extension
            $path = $path.'-'.$name.'.'.$ext;

            /** @var \Intervention\Image\Interfaces\ImageInterface $image */
            $image = InterventionImage::decodePath($file->getRealPath());

            $action = $size[2] ?? 'resize';
            $action = $this->thumbnailActions[$action] ?? $action;

            // Resize image with aspect ratio
            $image->$action($size[0], $size[1])
                ->resizeCanvas($size[0], $size[1], '#ffffff', Alignment::CENTER);

            $contents = $image->encode()->toString();

            if (!is_null($this->storagePermission)) {
                $this->storage->put("{$this->getDirectory()}/{$path}", $contents, $this->storagePermission);
            } else {
                $this->storage->put("{$this->getDirectory()}/{$path}", $contents);
            }
        }

        $this->destroyThumbnail();

        return $this;
    }
}
