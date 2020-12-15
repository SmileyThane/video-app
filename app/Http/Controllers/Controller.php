<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Streaming\FFMpeg;
use Streaming\Representation;
use Streaming\HLSFlag;
use Streaming;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;



    public function freshStream(): void
    {
        $config = [
            'ffmpeg.binaries'  => '/usr/bin/ffmpeg',
            'ffprobe.binaries' => '/usr/bin/ffprobe',
            'timeout'          => 3600,
            'ffmpeg.threads'   => 12,
        ];

        $log = new Logger('FFmpeg_Streaming');
        $log->pushHandler(new StreamHandler('/var/log/ffmpeg-streaming.log'));

        $ffmpeg = FFMpeg::create($config, $log);
//
//        $config = [
//            'version'     => 'latest',
//            'region'      => 'us-west-1', // the region of your cloud server
//            'credentials' => [
//                'key'    => 'access-key-id', // the key to authorize you on the server
//                'secret' => 'secret-access-key', // the secret to access to the cloud
//            ]
//        ];
//
//        $s3 = new Streaming\Clouds\S3($config);
//
//        $from_s3 = [
//            'cloud' => $s3,
//            'options' => [
//                'Bucket' => 'ojowo-files',
//                'Key' => 'vojowo/006822973121ff9dac6332358fc3d77d/videos/original.mp4'
//            ]
//        ];
//        $video = $ffmpeg->openFromCloud($from_s3);

        $video = $ffmpeg->open('https://ojowo-files.s3.amazonaws.com/vojowo/006822973121ff9dac6332358fc3d77d/videos/original.mp4');

//        $video->dash()
//            ->setSegDuration(3) // Default value is 10
//            ->setAdaption('id=0,streams=v id=1,streams=a')
//            ->x264()
//            ->autoGenerateRepresentations()
//            ->save('/var/www/video-app/public/dash-stream.mpd');
        $save_to = '/var/www/video-app/public/key';
        $url = 'http://localhost:8000/key';
        $video->hls()
            ->x264()
//            ->encryption($save_to, $url, 10)
            ->fragmentedMP4()
            ->setHlsListSize(5)
            ->setFlags([HLSFlag::DELETE_SEGMENTS])
            ->setHlsTime(10)
            ->setHlsAllowCache(false)
            ->autoGenerateRepresentations([480, 360])
            ->save('/var/www/video-app/public/dash-stream.m3u8');

    }
}
