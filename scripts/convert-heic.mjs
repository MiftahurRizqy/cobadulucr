import fs from 'node:fs/promises';
import path from 'node:path';
import convert from 'heic-convert';
import exifr from 'exifr';

const [, , inputPath, outputPath, metadataPath] = process.argv;

if (!inputPath || !outputPath || !metadataPath) {
    throw new Error('Input dan output file wajib diberikan.');
}

const input = await fs.readFile(inputPath);
const exif = await exifr.parse(inputPath, { tiff: true, exif: true, gps: true, ifd0: true }).catch(() => null);
const output = await convert({
    buffer: input,
    format: 'JPEG',
    // Preserve camera detail. Further storage optimization is performed once
    // by Laravel, so the HEIC source must not be aggressively compressed here.
    quality: 0.96,
});

await fs.mkdir(path.dirname(outputPath), { recursive: true });
await fs.writeFile(outputPath, output);
await fs.writeFile(metadataPath, JSON.stringify(exif ? {
    captured_at: (exif.DateTimeOriginal || exif.CreateDate || exif.ModifyDate)?.toISOString?.() || null,
    camera_make: exif.Make || null,
    camera_model: exif.Model || exif.HostComputer || null,
    software: exif.Software || null,
    gps_latitude: Number.isFinite(exif.latitude) ? exif.latitude : null,
    gps_longitude: Number.isFinite(exif.longitude) ? exif.longitude : null,
    gps_altitude: Number.isFinite(exif.GPSAltitude) ? exif.GPSAltitude : null,
    exif_date_raw: (exif.DateTimeOriginal || exif.CreateDate || exif.ModifyDate)?.toISOString?.() || null,
    image_width: exif.ExifImageWidth || null,
    image_height: exif.ExifImageHeight || null,
} : {}));
