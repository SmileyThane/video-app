<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Streaming;
use Streaming\FFMpeg;
use Streaming\HLSFlag;
use Streaming\Representation;
use Throwable;

class VideoController extends Controller
{

    private $ffmpeg;
    private $video;
    private $bucket;

    public function upload(Request $request)
    {
        if ($request->file('file')) {
            $uri = '';
            $name = $request->file('file')->getClientOriginalName();
            $typeId = $this->getTypeByMime($request->file('file')->getClientMimeType());
            if ($typeId === 3) {
                $saveTo = self::getRandomString(12);
                $path = storage_path('app/public/' . $saveTo);
                $request->file('file')->storeAs('public/' . $saveTo, $name);
                $this->setDriver();
                $this->video = $this->ffmpeg->open($path . '/' . $name);
                $this->run($saveTo);
                $files = Storage::files('public/' . $saveTo);
                foreach ($files as $file) {
                    $file = Storage::disk('b2')->put(
                        $saveTo . '/' . File::basename($file),
                        File::get(storage_path('app/' . $file))
                    );
                    if (File::extension($file) == 'm3u8') {
                        $uri = 'https://f000.backblazeb2.com/file/video-app/' . $saveTo . '/' . File::basename($file);
                    }
                }
            } else {
                $file = Storage::disk('s3')->put($name, $request->file('file'));
                $uri = 'https://f000.backblazeb2.com/file/video-app/' . $file;
            }
            return response()->json(['success' => true,
                'data' => [
                    'type_id' => $typeId,
                    'name' => $name,
                    'path' => $uri,
                    'extension' => $request->file('file')->getClientOriginalExtension(),
                    'mime_type' => $request->file('file')->getMimeType(),
                    'size' => $request->file('file')->getSize(),
                ]
            ]);
        } else {
            return response()->json(['success' => false]);
        }

    }

    private function getTypeByMime($mime)
    {
        if (strpos($mime, 'audio') !== false) {
            return 5;
        }
        if (strpos($mime, 'video') !== false || strpos($mime, 'octet-stream') !== false) {
            return 3;
        }
        if (strpos($mime, 'document') !== false) {
            return 4;
        }
        if (strpos($mime, 'pdf') !== false) {
            return 4;
        }
        if (strpos($mime, 'image') !== false) {
            return 2;
        }
        return 1;
    }

    private function setDriver(): void
    {
        $conversionConfig = [
            'ffmpeg.binaries' => '/usr/bin/ffmpeg',
            'ffprobe.binaries' => '/usr/bin/ffprobe',
            'timeout' => 7200,
            'ffmpeg.threads' => 8,
        ];
        $this->ffmpeg = FFMpeg::create($conversionConfig);
    }

    public function run($pathToFile, $personalBucket = null): ?bool
    {
        try {

//            $this->initialize($pathToFile, $personalBucket);
            Log::warning("Operation for $personalBucket __ $pathToFile successful!");
            return $this->convert(self::getRandomString(12), false, $personalBucket);
        } catch (Throwable $th) {
            Log::warning($th);
            return false;
        }

    }

    private function convert($saveTo = null, $saveLocal = false, $personalBucket = null): string
    {

//        if ($saveLocal === true) {
//            $url = env('APP_URL') . '/storage/' . $saveTo;
//        $saveTo = storage_path('app/public/' . $saveTo);
//
//        } else {
//        $dest = 's3://' . env('VIDEO_SAVE_BUCKET') . '/' . $saveTo;
//        $filename = 'original.m3u8';
//        $to_s3 = [
//            'cloud' => $this->bucket,
//            'options' => [
//                'dest' => $dest,
//                'filename' => ''
//            ]
//        ];
//        $url = $dest . '/' . $filename;
//        }

        $r_480p = (new Representation)->setKiloBitrate(400)->setResize(854, 480);

        $this->video->hls()
            ->x264()
            ->fragmentedMP4()
//            ->setHlsListSize(5)
            ->setFlags([HLSFlag::DELETE_SEGMENTS])
            ->setHlsTime(30)
            ->setHlsAllowCache(false)
            ->addRepresentations([$r_480p])
            ->save();
//        echo "\n-----\n";
//        echo "Url: " . $url;
//        echo "\n-----\n";
        return $saveTo;
    }

    private function initialize($pathToFile, $personalBucket = null): void
    {
        if ($personalBucket) {
            $bucketConfig = [
                'version' => 'latest',
                'region' => env('VIDEO_BUCKET_REGION'),
                'credentials' => [
                    'key' => env('VIDEO_BUCKET_ACCESS_KEY'),
                    'secret' => env('VIDEO_BUCKET_SECRET_KEY'),
                ]
            ];
            $this->bucket = new Streaming\Clouds\S3($bucketConfig);
            $fileConfig = [
                'cloud' => $this->bucket,
                'options' => [
                    'Bucket' => $personalBucket,
                    'Key' => $pathToFile
                ]
            ];
            $this->video = $this->ffmpeg->openFromCloud($fileConfig);
//        } else {
//            $this->video = $this->ffmpeg->open($pathToFile);
        }

    }
}
