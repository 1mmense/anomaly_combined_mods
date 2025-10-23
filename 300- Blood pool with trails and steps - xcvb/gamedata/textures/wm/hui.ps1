$nvtt = "C:\Program Files\NVIDIA Corporation\NVIDIA Texture Tools\nvtt_export.exe"
$env:Path += ";C:\Program Files\ImageMagick-7.1.0-Q16-HDRI"
# & $nvtt -help
for ($i = 0; $i -le 358; $i = $i + 2) {
    magick convert .\wm_blood_step1.png -channel RGBA -separate -distort SRT "-$i" -shave 250 -resize 256 -combine ".\wm_blood_step_l_$i.png"
    & $nvtt -o ".\wm_blood_step_l_$i.dds" -f 18 -q 3 --no-mips --mip-filter 1 ".\wm_blood_step_l_$i.png"
    Remove-Item -Path ".\wm_blood_step_l_$i.png" -Force
    Copy-Item -Path ".\wm_blood_step_0.thm" -Destination ".\wm_blood_step_l_$i.thm" -Force

    magick convert .\wm_blood_step1.png -channel RGBA -separate -flop -distort SRT "-$i" -shave 250 -resize 256 -combine ".\wm_blood_step_r_$i.png"
    & $nvtt -o ".\wm_blood_step_r_$i.dds" -f 18 -q 3 --no-mips --mip-filter 1 ".\wm_blood_step_r_$i.png"
    Remove-Item -Path ".\wm_blood_step_r_$i.png" -Force
    Copy-Item -Path ".\wm_blood_step_0.thm" -Destination ".\wm_blood_step_r_$i.thm" -Force
}

$j = 2
for ($i = 3; $i -le 33; $i = $i + 3) {
    $r = $i * 10
    magick convert .\wm_blood_pool_1.dds -channel RGBA -separate -distort SRT "-$r" -combine ".\wm_blood_pool_$j.png"
    & $nvtt -o ".\wm_blood_pool_$j.dds" -f 18 -q 3 --no-mips --mip-filter 1 ".\wm_blood_pool_$j.png"
    Remove-Item -Path ".\wm_blood_pool_$j.png" -Force
    Copy-Item -Path ".\wm_blood_pool_1.thm" -Destination ".\wm_blood_pool_$j.thm" -Force
    $j = $j + 1
}

$j = 14
for ($i = 3; $i -le 33; $i = $i + 3) {
    $r = $i * 10
    magick convert .\wm_blood_pool_13.dds -channel RGBA -separate -distort SRT "-$r" -combine ".\wm_blood_pool_$j.png"
    & $nvtt -o ".\wm_blood_pool_$j.dds" -f 18 -q 3 --no-mips --mip-filter 1 ".\wm_blood_pool_$j.png"
    Remove-Item -Path ".\wm_blood_pool_$j.png" -Force
    Copy-Item -Path ".\wm_blood_pool_13.thm" -Destination ".\wm_blood_pool_$j.thm" -Force
    $j = $j + 1
}
