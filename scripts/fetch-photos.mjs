// Downloads the bakery photos listed in seed/photos.json into build/photos/
// (git ignored) and checks every file against its sha256. Files that are
// already there and match are not downloaded again.
//
//   node scripts/fetch-photos.mjs        (run by `make photos`)
//
// The photos are free stock photos (Unsplash License), credited in CREDITS.md
// and on a "Photo credits" page on both sites. They are downloaded at the
// original size on purpose: the BEFORE site serves them the way an owner
// would upload camera files.
import { createHash } from 'node:crypto';
import { mkdir, readFile, rename, writeFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const outDir = join(root, 'build', 'photos');
const manifest = JSON.parse(await readFile(join(root, 'seed', 'photos.json'), 'utf8'));

const sha256 = (buf) => createHash('sha256').update(buf).digest('hex');

await mkdir(outDir, { recursive: true });
let downloaded = 0;
let total = 0;
const failures = [];

for (const photo of manifest.photos) {
  const file = join(outDir, `${photo.id}.jpg`);
  if (existsSync(file) && sha256(await readFile(file)) === photo.sha256) {
    total += photo.bytes;
    continue;
  }
  let res;
  try {
    res = await fetch(photo.download, { redirect: 'follow' });
  } catch (err) {
    failures.push(`${photo.slug}: could not reach ${new URL(photo.download).host} (${err.cause?.code ?? err.message}). Are you offline?`);
    continue;
  }
  if (!res.ok) {
    failures.push(`${photo.slug}: HTTP ${res.status} for ${photo.download}`);
    continue;
  }
  const buf = Buffer.from(await res.arrayBuffer());
  const got = sha256(buf);
  if (got !== photo.sha256) {
    failures.push(
      `${photo.slug}: checksum mismatch (expected ${photo.sha256.slice(0, 12)}..., got ${got.slice(0, 12)}...). ` +
        `The file at ${photo.page} may have changed; check it and update seed/photos.json.`,
    );
    continue;
  }
  await writeFile(`${file}.part`, buf);
  await rename(`${file}.part`, file);
  downloaded += 1;
  total += buf.length;
  console.log(`${photo.slug.padEnd(28)} ${photo.width}x${photo.height}  ${(buf.length / 1e6).toFixed(1)} MB  ${photo.photographer}`);
}

if (failures.length) {
  console.error(`\nCould not get ${failures.length} of ${manifest.photos.length} photos:`);
  for (const f of failures) console.error(`  - ${f}`);
  console.error('\nThe sites need these photos. Connect to the internet and run `make photos` again.');
  process.exit(1);
}
console.log(`Photos ready: ${manifest.photos.length} files, ${(total / 1e6).toFixed(1)} MB in build/photos/ (${downloaded} downloaded, all checksums match).`);
