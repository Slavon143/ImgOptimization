<?php

class Optimize
{
    public $db;
    public $dirs = array(
        __DIR__ . '/themes',
        __DIR__ . '/uploads',
    );
    public $exts = ['png', 'jpg', 'jpeg'];
    public $filename;
    public $convertJpg = true;

    public function __construct()
    {
        $this->db = new PDO('mysql:dbname=optimize;host=localhost', 'root', '') or die("No connect to db");
        $this->createTable();
        $this->getImagesDir();
        $this->checkImg();
    }

    public function createTable()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `optimize_img` (

  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,

  `img` varchar(255) NOT NULL,

  `done` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',

  `error` varchar(255) NULL,

  `diff` int(11) UNSIGNED NOT NULL DEFAULT '0',

  PRIMARY KEY (`id`)

) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
    }

    public function glob_recursive($pattern, $flags = 0)

    {
        $files = glob($pattern, $flags);

        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $files = array_merge($files, self::glob_recursive($dir . '/' . basename($pattern), $flags));
        }
        return $files;
    }

    public function getImagesDir()
    {
        foreach ($this->dirs as $dir) {
            foreach ($this->glob_recursive($dir . '/*.*') as $file) {
                $ext = strtolower(substr(strrchr($file, '.'), 1));
                if (in_array($ext, $this->exts)) {
                    $file = str_replace(__DIR__, '', $file);
                    if (file_exists(__DIR__ . "/$file")) {
                        $getImg = $this->getImg($file);
                        if (empty($getImg)) {
                            $this->addImage($file);
                        }
                    }
                }
            }
        }
    }

    public function getImg($img)
    {
        $sth = $this->db->prepare("SELECT * FROM `optimize_img` WHERE `img` = ?");
        $sth->execute(array($img));
        return $sth->fetch(PDO::FETCH_ASSOC);
    }

    public function addImage($img)
    {
        $sth = $this->db->prepare("INSERT INTO `optimize_img` SET `img` = ?");
        $sth->execute(array($img));
    }

    public function getNotOptimImg()
    {
        $sth = $this->db->prepare("SELECT * FROM `optimize_img` WHERE `done` = 0 AND `error` IS NULL ORDER BY  `id` DESC");
        $sth->execute();
        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    public function optimize($data)
    {

        if ($this->convertJpg){
            $file = __DIR__ . $data["img"];
            $info = pathinfo($file);

            $this->convertToJpg($data['img'],__DIR__ . '/optimImg/' . $info['filename'], $data['id']);
        }else{
            $sendImg = $this->sendAPI($data);

            if (!empty($sendImg['dest'])){
                $diff = $sendImg['src_size'] - $sendImg['dest_size'];

                $save = $this->saveOptimImg($sendImg);
                if ($save){
                    $this->updateImg($diff,$data['id']);
                }
            }
        }
    }

    public function convertToJpg($filename,$fileOutput,$id){

        $image = imagecreatefrompng(__DIR__ . "/$filename");
        $bg = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
        imagealphablending($bg, TRUE);
        imagecopy($bg, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagedestroy($image);
        $quality = 50; // 0 = worst / smaller file, 100 = better / bigger file
        imagejpeg($bg, "$fileOutput" . ".jpg", $quality);
        imagedestroy($bg);

        if (file_exists("$fileOutput" . ".jpg")){
            $diff = filesize(__DIR__ . "/$filename") - filesize("$fileOutput" . ".jpg");
            $this->updateImg((int)$diff,$id);
        }
    }

    public function saveOptimImg($sendImg){
        $this->filename = str_replace(".png",".jpeg",$this->filename);

        $getOptimImg = file_get_contents($sendImg['dest']);
        if ($getOptimImg){
            file_put_contents(__DIR__ . "/optimImg/{$this->filename}",$getOptimImg);
            return true;
        }
    }

    public function checkImg()
    {
        $getNotImg = $this->getNotOptimImg();
        if (!empty($getNotImg)) {
            foreach ($getNotImg as $item) {
                if (file_exists(__DIR__ . "/{$item['img']}")) {
                    $this->optimize($item);
                } else {
                    $this->delete($item['id']);
                }
            }
        }
    }

    public function updateImg($diff,$id){
        $sth = $this->db->prepare("UPDATE `optimize_img` SET `done` = 1, `diff` = ? WHERE `id` = ?");
        $sth->execute(array($diff, $id));
    }

    public function delete($id)
    {
        $sth = $this->db->prepare("DELETE FROM `optimize_img` WHERE `id` = ?");
        $sth->execute(array($id));
    }

    public function sendAPI($data)
    {
        $file = __DIR__ . $data["img"];
        $mime = mime_content_type($file);
        $info = pathinfo($file);
        $this->filename = $info['basename'];

        $output = new CURLFile($file, $mime, $this->filename);
        $dataSend = array("files" => $output);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.resmush.it/?qlty=70');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataSend);
        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            $result = curl_error($ch);
            $this->addError($result,$data['id']);
        }else{
            curl_close($ch);
            $res = json_decode($result, JSON_OBJECT_AS_ARRAY);
            if (!empty($res['error'])){
                $this->addError($res['error_long'],$data['id']);
            }
            return $res;
        }

    }

    public function addError($error,$id){
        $sth = $this->db->prepare("UPDATE `optimize_img` SET `error` = ? WHERE `id` = ?");
        $sth->execute(array($error, $id));
    }
}


$optimize = new Optimize();

