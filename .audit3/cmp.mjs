import { PNG } from 'pngjs';
import fs from 'fs';
const f = process.argv[2];
const a = PNG.sync.read(fs.readFileSync('base/' + f));
const b = PNG.sync.read(fs.readFileSync('after/' + f));
let minY = 1e9, maxY = -1, minX = 1e9, maxX = -1;
for (let y = 0; y < a.height; y++) for (let x = 0; x < a.width; x++) {
  const i = (y*a.width+x)*4;
  if (Math.abs(a.data[i]-b.data[i])>2||Math.abs(a.data[i+1]-b.data[i+1])>2||Math.abs(a.data[i+2]-b.data[i+2])>2) {
    if(y<minY)minY=y; if(y>maxY)maxY=y; if(x<minX)minX=x; if(x>maxX)maxX=x;
  }
}
console.log(`${f}: diff bbox x=${minX}..${maxX} y=${minY}..${maxY}`);
