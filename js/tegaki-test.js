/*! tegaki.js, MIT License */'use strict';var TegakiStrings = {
  // Messages
  badDimensions: 'Invalid dimensions.',
  promptWidth: 'Canvas width in pixels',
  promptHeight: 'Canvas height in pixels',
  confirmDelLayers: 'Delete selected layers?',
  confirmMergeLayers: 'Merge selected layers?',
  tooManyLayers: 'Layer limit reached.',
  errorLoadImage: 'Could not load the image.',
  noActiveLayer: 'No active layer.',
  hiddenActiveLayer: 'The active layer is not visible.',
  confirmCancel: 'Are you sure? Your work will be lost.',
  confirmChangeCanvas: 'Are you sure? Changing the canvas will clear all layers and history and disable replay recording.',
  
  // Controls
  color: 'Color',
  size: 'Size',
  alpha: 'Opacity',
  flow: 'Flow',
  zoom: 'Zoom',
  layers: 'Layers',
  switchPalette: 'Switch color palette',
  paletteSlotReplace: 'Right click to replace with the current color',
  
  // Layers
  layer: 'Layer',
  addLayer: 'Add layer',
  delLayers: 'Delete layers',
  mergeLayers: 'Merge layers',
  moveLayerUp: 'Move up',
  moveLayerDown: 'Move down',
  toggleVisibility: 'Toggle visibility',
  
  // Menu bar
  newCanvas: 'New',
  open: 'Open',
  save: 'Save',
  saveAs: 'Save As',
  export: 'Export',
  undo: 'Undo',
  redo: 'Redo',
  close: 'Close',
  finish: 'Finish',
  
  // Tool modes
  tip: 'Tip',
  pressure: 'Pressure',
  preserveAlpha: 'Preserve Alpha',
  
  // Tools
  pen: 'Pen',
  pencil: 'Pencil',
  airbrush: 'Airbrush',
  pipette: 'Pipette',
  blur: 'Blur',
  eraser: 'Eraser',
  bucket: 'Bucket',
  tone: 'Tone',
  
  // Replay
  gapless: 'Gapless',
  play: 'Play',
  pause: 'Pause',
  rewind: 'Rewind',
  slower: 'Slower',
  faster: 'Faster',
  recordingEnabled: 'Recording replay',
  errorLoadReplay: 'Could not load the replay: ',
  loadingReplay: 'Loading replay…',
};
class TegakiTool {
  constructor() {
    this.id = 0;
    
    this.name = null;
    
    this.keybind = null;
    
    this.useFlow = false;
    
    this.useSizeDynamics = false;
    this.useAlphaDynamics = false;
    this.useFlowDynamics = false;
    
    this.usePreserveAlpha = false;
    
    this.step = 0.0;
    
    this.size = 1;
    this.alpha = 1.0;
    this.flow = 1.0;
    
    this.useSize = true;
    this.useAlpha = true;
    this.useFlow = true;
    
    this.noCursor = false;
    
    this.color = '#000000';
    this.rgb = [0, 0, 0];
    
    this.brushSize = 0;
    this.brushAlpha = 0.0;
    this.brushFlow = 0.0;
    this.stepSize = 0.0;
    this.center = 0.0;
    
    this.sizeDynamicsEnabled = false;
    this.alphaDynamicsEnabled = false;
    this.flowDynamicsEnabled = false;
    this.preserveAlphaEnabled = false;
    
    this.tip = -1;
    this.tipList = null;
    
    this.stepAcc = 0;
    
    this.shapeCache = null;
    
    this.kernel = null;
  }
  
  brushFn(x, y, offsetX, offsetY) {}
  
  start(posX, posY) {}
  
  commit() {}
  
  draw(posX, posY) {}
  
  usesDynamics() {
    return this.useSizeDynamics || this.useAlphaDynamics || this.useFlowDynamics;
  }
  
  enabledDynamics() {
    return this.sizeDynamicsEnabled || this.alphaDynamicsEnabled || this.flowDynamicsEnabled;
  }
  
  setSize(size) {
    this.size = size;
  }
  
  setAlpha(alpha) {
    this.alpha = alpha;
    this.brushAlpha = alpha;
  }
  
  setFlow(flow) {
    this.flow = flow;
    this.brushFlow = this.easeFlow(flow);
  }
  
  easeFlow(flow) {
    return flow;
  }
  
  setColor(hex) {
    this.rgb = $T.hexToRgb(hex);
  }
  
  setSizeDynamics(flag) {
    if (!this.useSizeDynamics) {
      return;
    }
    
    if (!flag) {
      this.setSize(this.size);
    }
    
    this.sizeDynamicsEnabled = flag;
  }
  
  setAlphaDynamics(flag) {
    if (!this.useAlphaDynamics) {
      return;
    }
    
    if (!flag) {
      this.setAlpha(this.alpha);
    }
    
    this.alphaDynamicsEnabled = flag;
  }
  
  setFlowDynamics(flag) {
    if (!this.useFlowDynamics) {
      return;
    }
    
    if (!flag) {
      this.setFlow(this.flow);
    }
    
    this.flowDynamicsEnabled = flag;
  }
  
  setPreserveAlpha(flag) {
    this.preserveAlphaEnabled = flag;
  }
  
  set() {
    this.setAlpha(this.alpha);
    this.setFlow(this.flow);
    this.setSize(this.size);
    this.setColor(Tegaki.toolColor);
    
    Tegaki.onToolChanged(this);
  }
}
class TegakiBrush extends TegakiTool {
  constructor() {
    super();
  }
  
  generateShape(size) {}
  
  brushFn(x, y, offsetX, offsetY) {
    var aData, gData, bData, aWidth, canvasWidth, canvasHeight,
      kernel, xx, yy, ix, iy,
      pa, ka, a, sa,
      kr, kg, kb,
      r, g, b,
      pr, pg, pb,
      px, ba,
      brushSize, brushAlpha, brushFlow, preserveAlpha;
    
    preserveAlpha = this.preserveAlphaEnabled;
    
    kernel = this.kernel;
    
    brushAlpha = this.brushAlpha;
    brushFlow = this.brushFlow;
    brushSize = this.brushSize;
    
    aData = Tegaki.activeLayer.imageData.data;
    gData = Tegaki.ghostBuffer.data;
    bData = Tegaki.blendBuffer.data;
    
    canvasWidth = Tegaki.baseWidth;
    canvasHeight = Tegaki.baseHeight;
    
    aWidth = canvasWidth;
    
    kr = this.rgb[0];
    kg = this.rgb[1];
    kb = this.rgb[2];
    
    for (yy = 0; yy < brushSize; ++yy) {
      iy = y + yy + offsetY;
      
      if (iy < 0 || iy >= canvasHeight) {
        continue;
      }
      
      for (xx = 0; xx < brushSize; ++xx) {
        ix = x + xx + offsetX;
        
        if (ix < 0 || ix >= canvasWidth) {
          continue;
        }
        
        ka = kernel[(yy * brushSize + xx) * 4 + 3] / 255;
        
        if (ka <= 0.0) {
          continue;
        }
        
        px = (iy * canvasWidth + ix) * 4;
        
        sa = bData[px + 3] / 255;
        sa = sa + ka * brushFlow * (brushAlpha - sa);
        
        ba = Math.ceil(sa * 255);
        
        if (ba > bData[px + 3]) {
          if (bData[px] === 0) {
            gData[px] = aData[px];
            gData[px + 1] = aData[px + 1];
            gData[px + 2] = aData[px + 2];
            gData[px + 3] = aData[px + 3];
          }
          
          bData[px] = 1;
          bData[px + 3] = ba;
          
          pr = gData[px];
          pg = gData[px + 1];
          pb = gData[px + 2];
          pa = gData[px + 3] / 255;
          
          a = pa + sa - pa * sa;
          
          r = ((kr * sa) + (pr * pa) * (1 - sa)) / a;
          g = ((kg * sa) + (pg * pa) * (1 - sa)) / a;
          b = ((kb * sa) + (pb * pa) * (1 - sa)) / a;
          
          aData[px] = (kr > pr) ? Math.ceil(r) : Math.floor(r);
          aData[px + 1] = (kg > pg) ? Math.ceil(g) : Math.floor(g);
          aData[px + 2] = (kb > pb) ? Math.ceil(b) : Math.floor(b);
          
          if (!preserveAlpha) {
            aData[px + 3] = Math.ceil(a * 255);
          }
        }
      }
    }
  }
  
  generateShapeCache(force) {
    var i, shape;
    
    if (!this.shapeCache) {
      this.shapeCache = new Array(Tegaki.maxSize);
    }
    
    if (this.shapeCache[0] && !force) {
      return;
    }
    
    for (i = 0; i < Tegaki.maxSize; ++i) {
      shape = this.generateShape(i + 1);
      this.shapeCache[i] = shape;
      this.setShape(shape);
    }
  }
  
  updateDynamics(t) {
    var pressure, shape, val;
    
    pressure = TegakiPressure.lerp(t);
    
    if (this.sizeDynamicsEnabled) {
      val = Math.ceil(pressure * this.size);
      
      if (val === 0) {
        return false;
      }
      
      shape = this.shapeCache[val - 1];
      
      this.setShape(shape);
    }
    
    if (this.alphaDynamicsEnabled) {
      val = this.alpha * pressure;
      
      if (val <= 0) {
        return false;
      }
      
      this.brushAlpha = val;
    }
    
    if (this.flowDynamicsEnabled) {
      val = this.flow * pressure;
      
      if (val <= 0) {
        return false;
      }
      
      this.brushFlow = this.easeFlow(val);
    }
    
    return true;
  }
  
  start(posX, posY) {
    var sampleX, sampleY;
    
    this.stepAcc = 0;
    this.posX = posX; 
    this.posY = posY;
    
    if (this.enabledDynamics()) {
      if (!this.updateDynamics(1.0)) {
        return;
      }
    }
    
    sampleX = posX - this.center;
    sampleY = posY - this.center;
    
    this.readImageData(sampleX, sampleY, this.brushSize, this.brushSize);
    
    this.brushFn(0, 0, sampleX, sampleY);
    
    this.writeImageData(sampleX, sampleY, this.brushSize, this.brushSize);
  }
  
  commit() {
    Tegaki.clearBuffers();
  }
  
  draw(posX, posY) {
    var mx, my, fromX, fromY, sampleX, sampleY, dx, dy, err, derr, stepAcc,
      lastX, lastY, distBase, shape, center, brushSize, t, tainted, w, h;
    
    stepAcc = this.stepAcc;
    
    fromX = this.posX;
    fromY = this.posY;
    
    if (fromX < posX) { dx = posX - fromX; sampleX = fromX; mx = 1; }
    else { dx = fromX - posX; sampleX = posX; mx = -1; }
    
    if (fromY < posY) { dy = posY - fromY; sampleY = fromY; my = 1; }
    else { dy = fromY - posY; sampleY = posY; my = -1; }
    
    if (this.enabledDynamics()) {
      distBase = Math.sqrt((posX - fromX) * (posX - fromX) + (posY - fromY) * (posY - fromY));
    }
        
    if (this.sizeDynamicsEnabled) {
      shape = this.shapeCache[this.size - 1];
      center = shape.center;
      brushSize = shape.brushSize;
    }
    else {
      center = this.center;
      brushSize = this.brushSize;
    }
    
    sampleX -= center;
    sampleY -= center;
    
    w = dx + brushSize;
    h = dy + brushSize;
    
    this.readImageData(sampleX, sampleY, w, h);
    
    err = (dx > dy ? dx : (dy !== 0 ? -dy : 0)) / 2;
    
    if (dx !== 0) {
      dx = -dx;
    }
    
    tainted = false;
    
    lastX = fromX;
    lastY = fromY;
    
    while (true) {
      stepAcc += Math.max(Math.abs(lastX - fromX), Math.abs(lastY - fromY));
      
      lastX = fromX;
      lastY = fromY;
      
      if (stepAcc >= this.stepSize) {
        if (this.enabledDynamics()) {
          if (distBase > 0) {
            t = 1.0 - (Math.sqrt((posX - fromX) * (posX - fromX) + (posY - fromY) * (posY - fromY)) / distBase);
          }
          else {
            t = 0.0;
          }
          
          if (this.updateDynamics(t)) {
            this.brushFn(fromX - this.center - sampleX, fromY - this.center - sampleY, sampleX, sampleY);
            tainted = true;
          }
        }
        else {
          this.brushFn(fromX - this.center - sampleX, fromY - this.center - sampleY, sampleX, sampleY);
          tainted = true;
        }
        
        stepAcc = 0;
      }
      
      if (fromX === posX && fromY === posY) {
        break;
      }
      
      derr = err;
      
      if (derr > dx) { err -= dy; fromX += mx; }
      if (derr < dy) { err -= dx; fromY += my; }
    }
    
    this.stepAcc = stepAcc;
    this.posX = posX; 
    this.posY = posY;
    
    if (tainted) {
      this.writeImageData(sampleX, sampleY, w, h);
    }
  }
  
  writeImageData(x, y, w, h) {
    Tegaki.activeLayer.ctx.putImageData(Tegaki.activeLayer.imageData, 0, 0, x, y, w, h);
  }
  
  readImageData(x, y, w, h) {}
  
  setShape(shape) {
    this.center = shape.center;
    this.stepSize = shape.stepSize;
    this.brushSize = shape.brushSize;
    this.kernel = shape.kernel;
  }
  
  setSize(size) {
    this.size = size;
    
    if (this.sizeDynamicsEnabled) {
      this.generateShapeCache();
    }
    else {
      this.setShape(this.generateShape(size));
    }
  }
  
  setSizeDynamics(flag) {
    if (!this.useSizeDynamics) {
      return;
    }
    
    if (this.sizeDynamicsEnabled === flag) {
      return;
    }
    
    if (flag) {
      this.generateShapeCache();
    }
    else {
      this.setShape(this.generateShape(this.size));
    }
    
    this.sizeDynamicsEnabled = flag;
  }
  
  setTip(tipId) {
    this.tipId = tipId;
    
    if (this.sizeDynamicsEnabled) {
      this.generateShapeCache(true);
    }
    else {
      this.setShape(this.generateShape(this.size));
    }
  }
}
class TegakiPencil extends TegakiBrush {
  constructor() {
    super();
    
    this.id = 1;
    
    this.name = 'pencil';
    
    this.keybind = 'b';
    
    this.step = 0.01;
    
    this.useFlow = false;
    
    this.size = 1;
    this.alpha = 1.0;
    
    this.useSizeDynamics = true;
    this.useAlphaDynamics = true;
    this.usePreserveAlpha = true;
  }
  
  generateShape(size) {
    var e, x, y, imageData, data, c, color, r, rr;
    
    r = 0 | ((size) / 2);
    
    rr = 0 | ((size + 1) % 2);
    
    imageData = new ImageData(size, size);
    
    data = new Uint32Array(imageData.data.buffer);
    
    color = 0xFF000000;
    
    x = r;
    y = 0;
    e = 1 - r;
    c = r;
    
    while (x >= y) {
      data[c + x - rr + (c + y - rr) * size] = color;
      data[c + y - rr + (c + x - rr) * size] = color;
      
      data[c - y + (c + x - rr) * size] = color;
      data[c - x + (c + y - rr) * size] = color;
      
      data[c - y + (c - x) * size] = color;
      data[c - x + (c - y) * size] = color;
      
      data[c + y - rr + (c - x) * size] = color;
      data[c + x - rr + (c - y) * size] = color;
      
      ++y;
      
      if (e <= 0) {
        e += 2 * y + 1;
      }
      else {
        x--;
        e += 2 * (y - x) + 1;
      }
    }
    
    if (r > 0) {
      Tegaki.tools.bucket.fill(imageData, r, r, this.rgb, 1.0);
    }
    
    return {
      center: r,
      stepSize: Math.ceil(size * this.step),
      brushSize: size,
      kernel: imageData.data,
    };
  }
}
class TegakiAirbrush extends TegakiBrush {
  constructor() {
    super();
    
    this.id = 3;
    
    this.name = 'airbrush';
    
    this.keybind = 'a';
    
    this.step = 0.1;
    
    this.size = 32;
    this.alpha = 1.0;
    
    this.useSizeDynamics = true;
    this.useAlphaDynamics = true;
    this.useFlowDynamics = true;
    
    this.usePreserveAlpha = true;
  }
  
  easeFlow(flow) {
    return 1 - Math.sqrt(1 - flow);
  }
  
  generateShape(size) {
    var i, r, data, len, sqd, sqlen, hs, col, row,
      ecol, erow, a;
    
    r = size;
    size = size * 2;
    
    data = new ImageData(size, size).data;
    
    len = size * size * 4;
    sqlen = Math.sqrt(r * r);
    hs = Math.round(r);
    col = row = -hs;
    
    i = 0;
    while (i < len) {
      if (col >= hs) {
        col = -hs;
        ++row;
        continue;
      }
      
      ecol = col;
      erow = row;
      
      if (ecol < 0) { ecol = -ecol; }
      if (erow < 0) { erow = -erow; }
      
      sqd = Math.sqrt(ecol * ecol + erow * erow);
      
      if (sqd >= sqlen) {
        a = 0;
      }
      else if (sqd === 0) {
        a = 255;
      }
      else {
        a = (sqd / sqlen) + 0.1;
        
        if (a > 1.0) {
          a = 1.0;
        }
        
        a = (1 - (Math.exp(1 - 1 / a) / a)) * 255;
      }
      
      data[i + 3] = a;
      
      i += 4;
      
      ++col;
    }
    
    return {
      center: r,
      stepSize: Math.ceil(size * this.step),
      brushSize: size,
      kernel: data,
    };
  }
}
class TegakiPen extends TegakiBrush {
  constructor() {
    super();
    
    this.id = 2;
    
    this.name = 'pen';
    
    this.keybind = 'p';
    
    this.step = 0.05;
    
    this.size = 8;
    this.alpha = 1.0;
    this.flow = 1.0;
    
    this.useSizeDynamics = true;
    this.useAlphaDynamics = true;
    this.useFlowDynamics = true;
    
    this.usePreserveAlpha = true;
  }
  
  easeFlow(flow) {
    return 1 - Math.sqrt(1 - Math.pow(flow, 3));
  }

  generateShape(size) {
    var e, x, y, imageData, data, c, color, r, rr,
      f, ff, bSize, bData, i, ii, xx, yy, center, brushSize;
    
    center = Math.floor(size / 2) + 1;
    
    brushSize = size + 2;
    
    f = 4;
    
    ff = f * f;
    
    bSize = brushSize * f;
    
    r = Math.floor(bSize / 2);
    
    rr = Math.floor((bSize + 1) % 2);
    
    imageData = new ImageData(bSize, bSize);
    bData = new Uint32Array(imageData.data.buffer);
    
    color = 0x55000000;
    
    x = r;
    y = 0;
    e = 1 - r;
    c = r;
    
    while (x >= y) {
      bData[c + x - rr + (c + y - rr) * bSize] = color;
      bData[c + y - rr + (c + x - rr) * bSize] = color;
      
      bData[c - y + (c + x - rr) * bSize] = color;
      bData[c - x + (c + y - rr) * bSize] = color;
      
      bData[c - y + (c - x) * bSize] = color;
      bData[c - x + (c - y) * bSize] = color;
      
      bData[c + y - rr + (c - x) * bSize] = color;
      bData[c + x - rr + (c - y) * bSize] = color;
      
      ++y;
      
      if (e <= 0) {
        e += 2 * y + 1;
      }
      else {
        x--;
        e += 2 * (y - x) + 1;
      }
    }
    
    color = 0xFF000000;
    
    x = r - 3;
    y = 0;
    e = 1 - r;
    c = r;
    
    while (x >= y) {
      bData[c + x - rr + (c + y - rr) * bSize] = color;
      bData[c + y - rr + (c + x - rr) * bSize] = color;
      
      bData[c - y + (c + x - rr) * bSize] = color;
      bData[c - x + (c + y - rr) * bSize] = color;
      
      bData[c - y + (c - x) * bSize] = color;
      bData[c - x + (c - y) * bSize] = color;
      
      bData[c + y - rr + (c - x) * bSize] = color;
      bData[c + x - rr + (c - y) * bSize] = color;
      
      ++y;
      
      if (e <= 0) {
        e += 2 * y + 1;
      }
      else {
        x--;
        e += 2 * (y - x) + 1;
      }
    }
    
    if (r > 0) {
      Tegaki.tools.bucket.fill(imageData, r, r, this.rgb, 1.0);
    }
    
    bData = imageData.data;
    data = new ImageData(brushSize, brushSize).data;
    
    for (x = 0; x < brushSize; ++x) {
      for (y = 0; y < brushSize; ++y) {
        i = (y * brushSize + x) * 4 + 3;
        
        color = 0;
        
        for (xx = 0; xx < f; ++xx) {
          for (yy = 0; yy < f; ++yy) {
            ii = ((yy + y * f) * bSize + (xx + x * f)) * 4 + 3;
            color += bData[ii];
          }
        }
        
        data[i] = color / ff;
      }
    }
    
    return {
      center: center,
      stepSize: Math.ceil(size * this.step),
      brushSize: brushSize,
      kernel: data,
    };
  }
}
class TegakiBucket extends TegakiTool {
  constructor() {
    super();
    
    this.id = 4;
    
    this.name = 'bucket';
    
    this.keybind = 'g';
    
    this.step = 100.0;
    
    this.useSize = false;
    this.useFlow = false;
    
    this.noCursor = true;
  }
  
  fill(imageData, x, y, color, alpha) {
    var r, g, b, px, tr, tg, tb, ta, q, pxMap, yy, xx, yn, ys,
      yyy, yyn, yys, xd, data, w, h;
    
    w = imageData.width;
    h = imageData.height;
    
    r = color[0];
    g = color[1];
    b = color[2];
    
    px = (y * w + x) * 4;
    
    data = imageData.data;
    
    tr = data[px];
    tg = data[px + 1];
    tb = data[px + 2];
    ta = data[px + 3];
    
    pxMap = new Uint8Array(w * h * 4);
    
    q = [];
    
    q[0] = x;
    q[1] = y;
    
    while (q.length) {
      yy = q.pop();
      xx = q.pop();
      
      yn = (yy - 1);
      ys = (yy + 1);
      
      yyy = yy * w;
      yyn = yn * w;
      yys = ys * w;
      
      xd = xx;
      
      while (xd >= 0) {
        px = (yyy + xd) * 4;
        
        if (!this.testPixel(data, px, pxMap, tr, tg, tb, ta)) {
          break;
        }
        
        this.blendPixel(data, px, r, g, b, alpha);
        
        pxMap[px] = 1;
        
        if (yn >= 0) {
          px = (yyn + xd) * 4;
          
          if (this.testPixel(data, px, pxMap, tr, tg, tb, ta)) {
            q.push(xd);
            q.push(yn);
          }
        }
        
        if (ys < h) {
          px = (yys + xd) * 4;
          
          if (this.testPixel(data, px, pxMap, tr, tg, tb, ta)) {
            q.push(xd);
            q.push(ys);
          }
        }
        
        xd--;
      }
      
      xd = xx + 1;
      
      while (xd < w) {
        px = (yyy + xd) * 4;
        
        if (!this.testPixel(data, px, pxMap, tr, tg, tb, ta)) {
          break;
        }
        
        this.blendPixel(data, px, r, g, b, alpha);
        
        pxMap[px] = 1;
        
        if (yn >= 0) {
          px = (yyn + xd) * 4;
          
          if (this.testPixel(data, px, pxMap, tr, tg, tb, ta)) {
            q.push(xd);
            q.push(yn);
          }
        }
        
        if (ys < h) {
          px = (yys + xd) * 4;
          
          if (this.testPixel(data, px, pxMap, tr, tg, tb, ta)) {
            q.push(xd);
            q.push(ys);
          }
        }
        
        ++xd;
      }
    }
  }
  
  brushFn(x, y) {
    if (x < 0 || y < 0 || x >= Tegaki.baseWidth || y >= Tegaki.baseHeight) {
      return;
    }
    
    this.fill(Tegaki.activeLayer.imageData, x, y, this.rgb, this.alpha);
    
    // TODO: write back only the tainted rect
    Tegaki.activeLayer.ctx.putImageData(Tegaki.activeLayer.imageData, 0, 0);
  }
  
  blendPixel(data, px, r, g, b, a) {
    var sr, sg, sb, sa, dr, dg, db, da;
    
    sr = data[px];
    sg = data[px + 1];
    sb = data[px + 2];
    sa = data[px + 3] / 255;
    
    da = sa + a - sa * a;
    
    dr = ((r * a) + (sr * sa) * (1 - a)) / da;
    dg = ((g * a) + (sg * sa) * (1 - a)) / da;
    db = ((b * a) + (sb * sa) * (1 - a)) / da;
    
    data[px] = (r > sr) ? Math.ceil(dr) : Math.floor(dr);
    data[px + 1] = (g > sg) ? Math.ceil(dg) : Math.floor(dg);
    data[px + 2] = (b > sb) ? Math.ceil(db) : Math.floor(db);
    data[px + 3] = Math.ceil(da * 255);
  }
  
  testPixel(data, px, pxMap, tr, tg, tb, ta) {
    return !pxMap[px] && (data[px] == tr
      && data[++px] == tg
      && data[++px] == tb
      && data[++px] == ta)
    ;
  }
  
  start(x, y) {
    this.brushFn(x, y);
  }
  
  draw(x, y) {
    this.brushFn(x, y);
  }
  
  setSize(size) {}
}
class TegakiTone extends TegakiPencil {
  constructor() {
    super();
    
    this.id = 5;
    
    this.name = 'tone';
    
    this.keybind = 't';
    
    this.step = 0.01;
    
    this.useFlow = false;

    this.size = 8;
    this.alpha = 0.5;
    
    this.useSizeDynamics = true;
    this.useAlphaDynamics = true;
    this.usePreserveAlpha = true;
    
    this.matrix = [
      [0, 8, 2, 10],
      [12, 4, 14, 6],
      [3, 11, 1 ,9],
      [15, 7, 13, 5]
    ];
    
    this.mapCache = null;
    this.mapWidth = 0;
    this.mapHeight = 0;
  }
  
  start(x, y) {
    if (this.mapWidth !== Tegaki.baseWidth || this.mapHeight !== Tegaki.baseHeight) {
      this.generateMapCache(true);
    }
    
    super.start(x, y);
  }
  
  brushFn(x, y, offsetX, offsetY) {
    var data, kernel, brushSize, map, idx, preserveAlpha,
      px, mx, mapWidth, xx, yy, ix, iy, canvasWidth, canvasHeight;
    
    data = Tegaki.activeLayer.imageData.data;
    
    canvasWidth = Tegaki.baseWidth;
    canvasHeight = Tegaki.baseHeight;
    
    kernel = this.kernel;
    
    brushSize = this.brushSize;
    
    mapWidth = this.mapWidth;
    
    preserveAlpha = this.preserveAlphaEnabled;
    
    idx = Math.round(this.brushAlpha * 16) - 1;
    
    if (idx < 0) {
      return;
    }
    
    map = this.mapCache[idx];
    
    for (yy = 0; yy < brushSize; ++yy) {
      iy = y + yy + offsetY;
      
      if (iy < 0 || iy >= canvasHeight) {
        continue;
      }
      
      for (xx = 0; xx < brushSize; ++xx) {
        ix = x + xx + offsetX;
        
        if (ix < 0 || ix >= canvasWidth) {
          continue;
        }
        
        if (kernel[(yy * brushSize + xx) * 4 + 3] === 0) {
          continue;
        }
        
        mx = iy * canvasWidth + ix;
        px = mx * 4;
        
        if (map[mx] === 0) {
          data[px] = this.rgb[0];
          data[px + 1] = this.rgb[1];
          data[px + 2] = this.rgb[2];
          
          if (!preserveAlpha) {
            data[px + 3] = 255;
          }
        }
      }
    }
  }
  
  generateMap(w, h, idx) {
    var data, x, y;
    
    data = new Uint8Array(w * h);
    
    for (y = 0; y < h; ++y) {
      for (x = 0; x < w; ++x) {
        if (idx < this.matrix[y % 4][x % 4]) {
          data[w * y + x] = 1;
        }
      }
    }
    
    return data;
  }
  
  generateMapCache(force) {
    var i, cacheSize;
    
    cacheSize = this.matrix.length * this.matrix[0].length;
    
    if (!this.mapCache) {
      this.mapCache = new Array(cacheSize);
    }
    
    if (!force && this.mapCache[0]
      && this.mapWidth === Tegaki.baseWidth
      && this.mapHeight === Tegaki.baseHeight) {
      return;
    }
    
    this.mapWidth = Tegaki.baseWidth;
    this.mapHeight = Tegaki.baseHeight;
    
    for (i = 0; i < cacheSize; ++i) {
      this.mapCache[i] = this.generateMap(this.mapWidth, this.mapHeight, i);
    }
  }
  
  setAlpha(alpha) {
    super.setAlpha(alpha);
    this.generateMapCache();
  }
}
class TegakiPipette extends TegakiTool {
  constructor() {
    super();
    
    this.id = 6;
    
    this.name = 'pipette';
    
    this.keybind = 'i';
    
    this.step = 100.0;
    
    this.useSize = false;
    this.useAlpha = false;
    this.useFlow = false;
    
    this.noCursor = true;
  }
  
  start(posX, posY) {
    this.draw(posX, posY);
  }
  
  draw(posX, posY) {
    var c, ctx;
    
    if (true) {
      ctx = Tegaki.flatten().getContext('2d');
    }
    else {
      ctx = Tegaki.activeLayer.ctx;
    }
    
    c = $T.getColorAt(ctx, posX, posY);
    
    Tegaki.setToolColor(c);
  }
  
  set() {
    Tegaki.onToolChanged(this);
  }
  
  commit() {}
  
  setSize() {}
  
  setAlpha() {}
}
class TegakiBlur extends TegakiBrush {
  constructor() {
    super();
    
    this.id = 7;
    
    this.name = 'blur';
    
    this.step = 0.25;
    
    this.useFlow = false;
    
    this.size = 32;
    this.alpha = 0.5;
    
    this.useAlphaDynamics = true;
    this.usePreserveAlpha = false;
  }
  
  writeImageData(x, y, w, h) {
    var xx, yy, ix, iy, px, canvasWidth, aData, bData;
    
    aData = Tegaki.activeLayer.imageData.data;
    bData = Tegaki.blendBuffer.data;
    
    canvasWidth = Tegaki.baseWidth;
    
    for (xx = 0; xx < w; ++xx) {
      ix = x + xx;
      
      for (yy = 0; yy < h; ++yy) {
        iy = y + yy;
        
        px = (iy * canvasWidth + ix) * 4;
        
        aData[px] = bData[px];
        aData[px + 1] = bData[px + 1];
        aData[px + 2] = bData[px + 2];
        aData[px + 3] = bData[px + 3];
      }
    }
    
    super.writeImageData(x, y, w, h);
  }
  
  readImageData(x, y, w, h) {
    var xx, yy, ix, iy, px, canvasWidth, aData, bData;
    
    aData = Tegaki.activeLayer.imageData.data;
    bData = Tegaki.blendBuffer.data;
    
    canvasWidth = Tegaki.baseWidth;
    
    for (xx = 0; xx < w; ++xx) {
      ix = x + xx;
      
      for (yy = 0; yy < h; ++yy) {
        iy = y + yy;
        
        px = (iy * canvasWidth + ix) * 4;
        
        bData[px] = aData[px];
        bData[px + 1] = aData[px + 1];
        bData[px + 2] = aData[px + 2];
        bData[px + 3] = aData[px + 3];
      }
    }
  }
  
  brushFn(x, y, offsetX, offsetY) {
    var i, j, size, aData, bData, limX, limY,
      kernel, alpha, alpha0, ix, iy, canvasWidth, canvasHeight,
      sx, sy, r, g, b, a, kx, ky, px, pa, acc, aa;
    
    alpha0 = this.brushAlpha;
    alpha = alpha0 * alpha0 * alpha0;
    
    if (alpha <= 0.0) {
      return;
    }
    
    size = this.brushSize;
    
    kernel = this.kernel;
    
    aData = Tegaki.activeLayer.imageData.data;
    bData = Tegaki.blendBuffer.data;
    
    canvasWidth = Tegaki.baseWidth;
    canvasHeight = Tegaki.baseHeight;
    
    limX = canvasWidth - 1;
    limY = canvasHeight - 1;
    
    for (sx = 0; sx < size; ++sx) {
      ix = x + sx + offsetX;
      
      if (ix < 0 || ix >= canvasWidth) {
        continue;
      }
      
      for (sy = 0; sy < size; ++sy) {
        iy = y + sy + offsetY;
        
        if (iy < 0 || iy >= canvasHeight) {
          continue;
        }
        
        i = (sy * size + sx) * 4;
        
        px = (iy * canvasWidth + ix) * 4;
        
        if (kernel[i + 3] === 0 || ix <= 0 || iy <= 0 || ix >= limX || iy >= limY) {
          continue;
        }
        
        r = g = b = a = acc = 0;
        
        for (kx = -1; kx < 2; ++kx) {
          for (ky = -1; ky < 2; ++ky) {
            j = ((iy - ky) * canvasWidth + (ix - kx)) * 4;
            
            pa = aData[j + 3];
            
            if (kx === 0 && ky === 0) {
              aa = pa * alpha0;
              acc += alpha0;
            }
            else {
              aa = pa * alpha;
              acc += alpha;
            }
            
            r = r + aData[j] * aa; ++j;
            g = g + aData[j] * aa; ++j;
            b = b + aData[j] * aa;
            a = a + aa;
          }
        }
        
        a = a / acc;
        
        if (a <= 0.0) {
          continue;
        }
        
        bData[px] = Math.round((r / acc) / a);
        bData[px + 1] = Math.round((g / acc) / a);
        bData[px + 2] = Math.round((b / acc) / a);
        bData[px + 3] = Math.round(a);
      }
    }
  }
}

TegakiBlur.prototype.generateShape = TegakiPencil.prototype.generateShape;
class TegakiEraser extends TegakiBrush {
  constructor() {
    super();
    
    this.id = 8;
    
    this.name = 'eraser';
    
    this.keybind = 'e';
    
    this.step = 0.1;
    
    this.size = 8;
    this.alpha = 1.0;
    
    this.useFlow = false;
    
    this.useSizeDynamics = true;
    this.useAlphaDynamics = true;
    this.usePreserveAlpha = false;
    
    this.tipId = 0;
    this.tipList = [ 'pencil', 'pen', 'airbrush' ];
  }
  
  brushFn(x, y, offsetX, offsetY) {
    var aData, bData, gData, kernel, canvasWidth, canvasHeight,
      ka, ba, px, xx, yy, ix, iy,
      brushSize, brushAlpha;
    
    brushAlpha = this.brushAlpha;
    brushSize = this.brushSize;
    
    kernel = this.kernel;
    
    aData = Tegaki.activeLayer.imageData.data;
    gData = Tegaki.ghostBuffer.data;
    bData = Tegaki.blendBuffer.data;
    
    canvasWidth = Tegaki.baseWidth;
    canvasHeight = Tegaki.baseHeight;
    
    for (yy = 0; yy < brushSize; ++yy) {
      iy = y + yy + offsetY;
      
      if (iy < 0 || iy >= canvasHeight) {
        continue;
      }
      
      for (xx = 0; xx < brushSize; ++xx) {
        ix = x + xx + offsetX;
        
        if (ix < 0 || ix >= canvasWidth) {
          continue;
        }
        
        ka = kernel[(yy * brushSize + xx) * 4 + 3] / 255;
        
        px = (iy * canvasWidth + ix) * 4 + 3;
        
        if (gData[px] === 0) {
          gData[px] = aData[px];
        }
        
        ba = bData[px] / 255;
        ba = ba + ka * (brushAlpha - ba);
        
        bData[px] = Math.floor(ba * 255);
        aData[px] = Math.floor(gData[px] * (1 - ba));
      }
    }
  }
  
  generateShape(size) {
    if (this.tipId === 0) {
      return this.generateShapePencil(size);
    }
    else if (this.tipId === 1) {
      return this.generateShapePen(size);
    }
    else {
      return this.generateShapeAirbrush(size);
    }
  }
}

TegakiEraser.prototype.generateShapePencil = TegakiPencil.prototype.generateShape;
TegakiEraser.prototype.generateShapePen = TegakiPen.prototype.generateShape;
TegakiEraser.prototype.generateShapeAirbrush = TegakiAirbrush.prototype.generateShape;
var $T = {
  docEl: document.documentElement,
  
  id: function(id) {
    return document.getElementById(id);
  },
  
  cls: function(klass, root) {
    return (root || document).getElementsByClassName(klass);
  },
  
  on: function(o, e, h) {
    o.addEventListener(e, h, false);
  },
  
  off: function(o, e, h) {
    o.removeEventListener(e, h, false);
  },
  
  el: function(name) {
    return document.createElement(name);
  },
  
  frag: function() {
    return document.createDocumentFragment();
  },
  
  copyImageData(imageData) {
    return new ImageData(
      new Uint8ClampedArray(imageData.data),
      imageData.width
    );
  },
  
  copyCanvas: function(source, clone) {
    var canvas;
    
    if (!clone) {
      canvas = $T.el('canvas');
      canvas.width = source.width;
      canvas.height = source.height;
    }
    else {
      canvas = source.cloneNode(false);
    }
    
    canvas.getContext('2d').drawImage(source, 0, 0);
    
    return canvas;
  },
  
  clearCtx: function(ctx) {
    ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
  },
  
  hexToRgb: function(hex) {
    var c = hex.match(/^#([a-f0-9]{2})([a-f0-9]{2})([a-f0-9]{2})$/i);
    
    if (c) {
      return [
        parseInt(c[1], 16),
        parseInt(c[2], 16),
        parseInt(c[3], 16)
      ];
    }
    
    return null;
  },
  
  RgbToHex: function(r, g, b) {
    return '#' + ((1 << 24) + (r << 16) +  (g << 8) + b).toString(16).slice(1);
  },
  
  getColorAt: function(ctx, posX, posY) {
    var rgba = ctx.getImageData(posX, posY, 1, 1).data;
    
    return '#'
      + ('0' + rgba[0].toString(16)).slice(-2)
      + ('0' + rgba[1].toString(16)).slice(-2)
      + ('0' + rgba[2].toString(16)).slice(-2);
  },
  
  generateFilename: function() {
    return 'tegaki_' + (new Date()).toISOString().split('.')[0].replace(/[^0-9]/g, '_');
  },
  
  sortAscCb: function(a, b) {
    if (a > b) { return 1; }
    if (a < b) { return -1; }
    return 0;
  },
  
  sortDescCb: function(a, b) {
    if (a > b) { return -1; }
    if (a < b) { return 1; }
    return 0;
  },
  
  msToHms: function(ms) {
    var h, m, s, ary;
    
    s = 0 | (ms / 1000);
    h = 0 | (s / 3600);
    m = 0 | ((s - h * 3600) / 60);
    s = s - h * 3600 - m * 60;
    
    ary = [];
    
    if (h) {
      ary.push(h < 10 ? ('0' + h) : h);
    }
    
    if (m) {
      ary.push(m < 10 ? ('0' + m) : m);
    }
    else {
      ary.push('00');
    }
    
    if (s) {
      ary.push(s < 10 ? ('0' + s) : s);
    }
    else {
      ary.push('00');
    }
    
    return ary.join(':');
  },
  
  calcThumbSize(w, h, maxSide) {
    var r;
    
    if (w > maxSide) {
      r = maxSide / w;
      w = maxSide;
      h = h * r;
    }
    
    if (h > maxSide) {
      r = maxSide / h;
      h = maxSide;
      w = w * r;
    }
    
    return [Math.ceil(w), Math.ceil(h)];
  }
};
class TegakiBinReader {
  constructor(buf) {
    this.pos = 0;
    this.view = new DataView(buf);
    this.buf = buf;
  }
  
  readInt8() {
    var data = this.view.getInt8(this.pos);
    this.pos += 1;
    return data;
  }
  
  readUint8() {
    var data = this.view.getUint8(this.pos);
    this.pos += 1;
    return data;
  }
  
  readInt16() {
    var data = this.view.getInt16(this.pos);
    this.pos += 2;
    return data;
  }
  
  readUint16() {
    var data = this.view.getUint16(this.pos);
    this.pos += 2;
    return data;
  }
  
  readUint32() {
    var data = this.view.getUint32(this.pos);
    this.pos += 4;
    return data;
  }
  
  readFloat32() {
    var data = this.view.getFloat32(this.pos);
    this.pos += 4;
    return data;
  }
}

class TegakiBinWriter {
  constructor(buf) {
    this.pos = 0;
    this.view = new DataView(buf);
    this.buf = buf;
  }
  
  writeInt8(val) {
    this.view.setInt8(this.pos, val);
    this.pos += 1;
  }
  
  writeUint8(val) {
    this.view.setUint8(this.pos, val);
    this.pos += 1;
  }
  
  writeInt16(val) {
    this.view.setInt16(this.pos, val);
    this.pos += 2;
  }
  
  writeUint16(val) {
    this.view.setUint16(this.pos, val);
    this.pos += 2;
  }
  
  writeUint32(val) {
    this.view.setUint32(this.pos, val);
    this.pos += 4;
  }
  
  writeFloat32(val) {
    this.view.setFloat32(this.pos, val);
    this.pos += 4;
  }
}
var TegakiCursor = {
  size: 0,
  radius: 0,
  
  points: null,
  
  tmpCtx: null,
  
  cursorCtx: null,
  
  flatCtxAbove: null,
  flatCtxBelow: null,
  
  cached: false,
  
  init: function(w, h) {
    var el;
    
    this.tmpCtx = $T.el('canvas').getContext('2d');
    
    el = $T.el('canvas');
    el.id = 'tegaki-cursor-layer';
    el.width = w;
    el.height = h;
    Tegaki.layersCnt.appendChild(el);
    
    this.cursorCtx = el.getContext('2d');
    
    el = $T.el('canvas');
    el.width = w;
    el.height = h;
    this.flatCtxAbove = el.getContext('2d');
    
    el = $T.el('canvas');
    el.width = w;
    el.height = h;
    this.flatCtxBelow = el.getContext('2d');
  },
  
  updateCanvasSize: function(w, h) {
    this.cursorCtx.canvas.width = w;
    this.cursorCtx.canvas.height = h;
    
    this.flatCtxAbove.canvas.width = w;
    this.flatCtxAbove.canvas.height = h;
    
    this.flatCtxBelow.canvas.width = w;
    this.flatCtxBelow.canvas.height = h;
  },
  
  render: function(x, y) {
    var i, size, srcImg, srcData, destImg, destData, activeLayer;
    
    if (!this.cached) {
      this.buildCache();
    }
    
    size = this.size;
    x = x - this.radius;
    y = y - this.radius;
    
    $T.clearCtx(this.cursorCtx);
    $T.clearCtx(this.tmpCtx);
    
    this.tmpCtx.drawImage(this.flatCtxBelow.canvas, x, y, size, size, 0, 0, size, size);
    
    activeLayer = Tegaki.activeLayer;
    
    if (activeLayer.visible) {
      if (activeLayer.alpha < 1.0) {
        this.tmpCtx.globalAlpha = activeLayer.alpha;
        this.tmpCtx.drawImage(Tegaki.activeLayer.canvas, x, y, size, size, 0, 0, size, size);
        this.tmpCtx.globalAlpha = 1.0;
      }
      else {
        this.tmpCtx.drawImage(Tegaki.activeLayer.canvas, x, y, size, size, 0, 0, size, size);
      }
    }
    
    this.tmpCtx.drawImage(this.flatCtxAbove.canvas, x, y, size, size, 0, 0, size, size);
    
    srcImg = this.tmpCtx.getImageData(0, 0, size, size);
    srcData = new Uint32Array(srcImg.data.buffer);
    
    destImg = this.cursorCtx.createImageData(size, size);
    destData = new Uint32Array(destImg.data.buffer);
    
    for (i of this.points) {
      destData[i] = srcData[i] ^ 0x00FFFF7F;
    }
    
    this.cursorCtx.putImageData(destImg, x, y);
  },
  
  buildCache: function() {
    var i, layer, ctx, len, layerId;
    
    ctx = this.flatCtxBelow;
    ctx.globalAlpha = 1.0;
    $T.clearCtx(ctx);
    
    ctx.drawImage(Tegaki.canvas, 0, 0);
    
    layerId = Tegaki.activeLayer.id;
    
    for (i = 0, len = Tegaki.layers.length; i < len; ++i) {
      layer = Tegaki.layers[i];
      
      if (!layer.visible) {
        continue;
      }
      
      if (layer.id === layerId) {
        ctx = this.flatCtxAbove;
        ctx.globalAlpha = 1.0;
        $T.clearCtx(ctx);
        continue;
      }
      
      ctx.globalAlpha = layer.alpha;
      ctx.drawImage(layer.canvas, 0, 0);
    }
    
    this.cached = true;
  },
  
  invalidateCache() {
    this.cached = false;
  },
  
  destroy() {
    this.size = 0;
    this.radius = 0;
    this.points = null;
    this.tmpCtx = null;
    this.cursorCtx = null;
    this.flatCtxAbove = null;
    this.flatCtxBelow = null;
  },
  
  generate: function(size) {
    var e, x, y, c, r, rr, points;
    
    r = 0 | ((size) / 2);
    
    rr = 0 | ((size + 1) % 2);
    
    points = [];
    
    x = r;
    y = 0;
    e = 1 - r;
    c = r;
    
    while (x >= y) {
      points.push(c + x - rr + (c + y - rr) * size);
      points.push(c + y - rr + (c + x - rr) * size);
      
      points.push(c - y + (c + x - rr) * size);
      points.push(c - x + (c + y - rr) * size);
      
      points.push(c - y + (c - x) * size);
      points.push(c - x + (c - y) * size);
      
      points.push(c + y - rr + (c - x) * size);
      points.push(c + x - rr + (c - y) * size);
      
      ++y;
      
      if (e <= 0) {
        e += 2 * y + 1;
      }
      else {
        x--;
        e += 2 * (y - x) + 1;
      }
    }
    
    this.tmpCtx.canvas.width = size;
    this.tmpCtx.canvas.height = size;
    
    this.size = size;
    this.radius = r;
    this.points = points;
  }
};
var TegakiHistory = {
  maxSize: 50,
  
  undoStack: [],
  redoStack: [],
  
  pendingAction: null,
  
  clear: function() {
    this.undoStack = [];
    this.redoStack = [];
    this.pendingAction = null;
    
    this.onChange(0);
  },
  
  push: function(action) {
    if (!action) {
      return;
    }
    
    if (action.coalesce) {
      if (this.undoStack[this.undoStack.length - 1] instanceof action.constructor) {
        if (this.undoStack[this.undoStack.length - 1].coalesce(action)) {
          return;
        }
      }
    }
    
    this.undoStack.push(action);
    
    if (this.undoStack.length > this.maxSize) {
      this.undoStack.shift();
    }
    
    if (this.redoStack.length > 0) {
      this.redoStack = [];
    }
    
    this.onChange(0);
  },
  
  undo: function() {
    var action;
    
    if (!this.undoStack.length) {
      return;
    }
    
    action = this.undoStack.pop();
    
    action.undo();
    
    this.redoStack.push(action);
    
    this.onChange(-1);
  },
  
  redo: function() {
    var action;
    
    if (!this.redoStack.length) {
      return;
    }
    
    action = this.redoStack.pop();
    
    action.redo();
    
    this.undoStack.push(action);
    
    this.onChange(1);
  },
  
  onChange: function(type) {
    Tegaki.onHistoryChange(this.undoStack.length, this.redoStack.length, type);
  },
  
  sortPosLayerCallback: function(a, b) {
    if (a[0] > b[0]) { return 1; }
    if (a[0] < b[0]) { return -1; }
    return 0;
  }
};

var TegakiHistoryActions = {
  Dummy: function() {
    this.undo = function() {};
    this.redo = function() {};
  },
  
  Draw: function(layerId) {
    this.coalesce = false;
    
    this.imageDataBefore = null;
    this.imageDataAfter = null;
    this.layerId = layerId;
  },
  
  DeleteLayers: function(layerPosMap, params) {
    var item;
    
    this.coalesce = false;
    
    this.layerPosMap = [];
    
    for (item of layerPosMap.sort(TegakiHistory.sortPosLayerCallback)) {
      item[1] = TegakiLayers.cloneLayer(item[1]);
      this.layerPosMap.push(item);
    }
    
    this.tgtLayerId = null;
    
    this.aLayerIdBefore = null;
    this.aLayerIdAfter = null;
    
    this.imageDataBefore = null;
    this.imageDataAfter = null;
    
    this.mergeDown = false;
    
    if (params) {
      for (let k in params) {
        this[k] = params[k];
      }
    }
  },
  
  AddLayer: function(params, aLayerIdBefore, aLayerIdAfter) {
    this.coalesce = false;
    
    this.layer = params;
    this.layerId = params.id;
    this.aLayerIdBefore = aLayerIdBefore;
    this.aLayerIdAfter = aLayerIdAfter;
  },
  
  MoveLayers: function(layers, belowPos, activeLayerId) {
    this.coalesce = false;
    
    this.layers = layers;
    this.belowPos = belowPos;
    this.aLayerId = activeLayerId;
  },
  
  SetLayersAlpha: function(layerAlphas, newAlpha) {
    this.layerAlphas = layerAlphas;
    this.newAlpha = newAlpha;
  },
  
  SetLayerName: function(id, oldName, newName) {
    this.layerId = id;
    this.oldName = oldName;
    this.newName = newName;
  }
};

// ---

TegakiHistoryActions.Draw.prototype.addCanvasState = function(imageData, type) {
  if (type) {
    this.imageDataAfter = $T.copyImageData(imageData);
  }
  else {
    this.imageDataBefore = $T.copyImageData(imageData);
  }
};

TegakiHistoryActions.Draw.prototype.exec = function(type) {
  var layer = TegakiLayers.getLayerById(this.layerId);
  
  if (type) {
    layer.ctx.putImageData(this.imageDataAfter, 0, 0);
    TegakiLayers.syncLayerImageData(layer, this.imageDataAfter);
  }
  else {
    layer.ctx.putImageData(this.imageDataBefore, 0, 0);
    TegakiLayers.syncLayerImageData(layer, this.imageDataBefore);
  }
  
  TegakiUI.updateLayerPreview(layer);
  TegakiLayers.setActiveLayer(this.layerId);
};

TegakiHistoryActions.Draw.prototype.undo = function() {
  this.exec(0);
};

TegakiHistoryActions.Draw.prototype.redo = function() {
  this.exec(1);
};

TegakiHistoryActions.DeleteLayers.prototype.undo = function() {
  var i, lim, refLayer, layer, pos, refId;
  
  for (i = 0, lim = this.layerPosMap.length; i < lim; ++i) {
    [pos, layer] = this.layerPosMap[i];
    
    layer = TegakiLayers.cloneLayer(layer);
    
    refLayer = Tegaki.layers[pos];
    
    if (refLayer) {
      if (refId = TegakiLayers.getLayerBelowId(refLayer.id)) {
        refId = refId.id;
      }
      
      TegakiUI.updateLayersGridAdd(layer, refId);
      TegakiUI.updateLayerPreview(layer);
      Tegaki.layersCnt.insertBefore(layer.canvas, refLayer.canvas);
      Tegaki.layers.splice(pos, 0, layer);
    }
    else {
      
      if (!Tegaki.layers[0]) {
        refLayer = Tegaki.layersCnt.children[0];
      }
      else {
        refLayer = Tegaki.layers[Tegaki.layers.length - 1].canvas;
      }
      
      TegakiUI.updateLayersGridAdd(layer, TegakiLayers.getTopLayerId());
      TegakiUI.updateLayerPreview(layer);
      Tegaki.layersCnt.insertBefore(layer.canvas, refLayer.nextElementSibling);
      Tegaki.layers.push(layer);
    }
  }
  
  if (this.tgtLayerId) {
    layer = TegakiLayers.getLayerById(this.tgtLayerId);
    layer.ctx.putImageData(this.imageDataBefore, 0, 0);
    TegakiLayers.syncLayerImageData(layer, this.imageDataBefore);
    TegakiLayers.setLayerAlpha(layer, this.tgtLayerAlpha);
    TegakiUI.updateLayerPreview(layer);
  }
  
  TegakiLayers.setActiveLayer(this.aLayerIdBefore);
};

TegakiHistoryActions.DeleteLayers.prototype.redo = function() {
  var layer, ids = [];
  
  for (layer of this.layerPosMap) {
    ids.unshift(layer[1].id);
  }
  
  if (this.tgtLayerId) {
    if (!this.mergeDown) {
      ids.unshift(this.tgtLayerId);
    }
    TegakiLayers.mergeLayers(new Set(ids));
  }
  else {
    TegakiLayers.deleteLayers(ids);
  }
  
  TegakiLayers.setActiveLayer(this.aLayerIdAfter);
};

TegakiHistoryActions.MoveLayers.prototype.undo = function() {
  var i, layer, stack, ref, posMap, len;
  
  stack = new Array(Tegaki.layers.length);
  
  posMap = {};
  
  for (layer of this.layers) {
    posMap[layer[1].id] = layer[0];
  }
  
  for (i = 0, len = Tegaki.layers.length; i < len; ++i) {
    layer = Tegaki.layers[i];
    
    if (posMap[layer.id] !== undefined) {
      Tegaki.layers.splice(i, 1);
      Tegaki.layers.splice(posMap[layer.id], 0, layer);
    }
  }
  
  TegakiUI.updayeLayersGridOrder();
  
  ref = Tegaki.layersCnt.children[0];
  
  for (i = Tegaki.layers.length - 1; i >= 0; i--) {
    layer = Tegaki.layers[i];
    Tegaki.layersCnt.insertBefore(layer.canvas, ref.nextElementSibling);
  }
  
  TegakiLayers.setActiveLayer(this.aLayerId);
};

TegakiHistoryActions.MoveLayers.prototype.redo = function() {
  var layer, layers = new Set();
  
  for (layer of this.layers.slice().reverse()) {
    layers.add(layer[1].id);
  }
  
  TegakiLayers.setActiveLayer(this.aLayerId);
  TegakiLayers.moveLayers(layers, this.belowPos);
};

TegakiHistoryActions.AddLayer.prototype.undo = function() {
  TegakiLayers.deleteLayers([this.layer.id]);
  TegakiLayers.setActiveLayer(this.aLayerIdBefore);
  Tegaki.layerCounter--;
};

TegakiHistoryActions.AddLayer.prototype.redo = function() {
  TegakiLayers.setActiveLayer(this.aLayerIdBefore);
  TegakiLayers.addLayer(this.layer);
  TegakiLayers.setActiveLayer(this.aLayerIdAfter);
};

TegakiHistoryActions.SetLayersAlpha.prototype.undo = function() {
  var id, layerAlpha, layer;

  for (layerAlpha of this.layerAlphas) {
    [id, layerAlpha] = layerAlpha;
    
    if (layer = TegakiLayers.getLayerById(id)) {
      TegakiLayers.setLayerAlpha(layer, layerAlpha);
    }
  }
  
  TegakiUI.updateLayerAlphaOpt();
};

TegakiHistoryActions.SetLayersAlpha.prototype.redo = function() {
  var id, layerAlpha, layer;

  for (layerAlpha of this.layerAlphas) {
    [id, layerAlpha] = layerAlpha;
    
    if (layer = TegakiLayers.getLayerById(id)) {
      TegakiLayers.setLayerAlpha(layer, this.newAlpha);
    }
  }
  
  TegakiUI.updateLayerAlphaOpt();
};

TegakiHistoryActions.SetLayersAlpha.prototype.coalesce = function(action) {
  var i;
  
  if (this.layerAlphas.length !== action.layerAlphas.length) {
    return false;
  }
  
  for (i = 0; i < this.layerAlphas.length; ++i) {
    if (this.layerAlphas[i][0] !== action.layerAlphas[i][0]) {
      return false;
    }
  }
  
  this.newAlpha = action.newAlpha;
  
  return true;
};

TegakiHistoryActions.SetLayerName.prototype.exec = function(type) {
  var layer = TegakiLayers.getLayerById(this.layerId);
  
  if (layer) {
    layer.name = type ? this.newName : this.oldName;
    TegakiUI.updateLayerName(layer);
  }
};

TegakiHistoryActions.SetLayerName.prototype.undo = function() {
  this.exec(0);
};

TegakiHistoryActions.SetLayerName.prototype.redo = function() {
  this.exec(1);
};
var TegakiKeybinds = {
  keyMap: {},
  
  captionMap: {},
  
  clear: function() {
    this.keyMap = {};
    this.captionMap = {};
  },
  
  bind: function(keys, klass, fn, id, caption) {
    this.keyMap[keys] = [klass, fn];
    
    if (id) {
      this.captionMap[id] = caption;
    }
  },
  
  getCaption(id) {
    return this.captionMap[id];
  },
  
  resolve: function(e) {
    var fn, mods, keys, el;
    
    el = e.target;
    
    if (el.nodeName == 'INPUT' && (el.type === 'text' || el.type === 'number')) {
      return;
    }
    
    mods = [];
    
    if (e.ctrlKey) {
      mods.push('ctrl');
    }
    
    if (e.shiftKey) {
      mods.push('shift');
    }
    
    keys = e.key.toLowerCase();
    
    if (mods[0]) {
      keys = mods.join('+') + '+' + keys;
    }
    
    fn = TegakiKeybinds.keyMap[keys];
    
    if (fn && !e.altKey && !e.metaKey) {
      e.preventDefault();
      e.stopPropagation();
      fn[0][fn[1]]();
    }
  },
};
var TegakiLayers = {
  cloneLayer: function(layer) {
    var newLayer = Object.assign({}, layer);
    
    newLayer.canvas = $T.copyCanvas(layer.canvas, true);
    newLayer.ctx = newLayer.canvas.getContext('2d');
    newLayer.imageData = $T.copyImageData(layer.imageData);
    
    return newLayer;
  },
  
  getCanvasById: function(id) {
    return $T.id('tegaki-canvas-' + id);
  },
  
  getActiveLayer: function() {
    return Tegaki.activeLayer;
  },
  
  getLayerPosById: function(id) {
    var i, layers = Tegaki.layers;
    
    for (i = 0; i < layers.length; ++i) {
      if (layers[i].id === id) {
        return i;
      }
    }
    
    return -1;
  },
  
  getTopFencedLayerId: function() {
    var i, id, layer, layers = Tegaki.layers;
    
    for (i = layers.length - 1; i >= 0; i--) {
      if (TegakiLayers.selectedLayersHas(layers[i].id)) {
        break;
      }
    }
    
    for (i = i - 1; i >= 0; i--) {
      if (!TegakiLayers.selectedLayersHas(layers[i].id)) {
        break;
      }
    }
    
    if (layer = layers[i]) {
      id = layer.id;
    }
    else {
      id = 0;
    }
    
    return id;
  },
  
  getSelectedEdgeLayerPos: function(top) {
    var i, layers = Tegaki.layers;
    
    if (top) {
      for (i = Tegaki.layers.length - 1; i >= 0; i--) {
        if (TegakiLayers.selectedLayersHas(layers[i].id)) {
          break;
        }
      }
    }
    else {
      for (i = 0; i < layers.length; ++i) {
        if (TegakiLayers.selectedLayersHas(layers[i].id)) {
          break;
        }
      }
      
      if (i >= layers.length) {
        i = -1;
      }
    }
    
    return i;
  },
  
  getTopLayer: function() {
    return Tegaki.layers[Tegaki.layers.length - 1];
  },
  
  getTopLayerId: function() {
    var layer = TegakiLayers.getTopLayer();
    
    if (layer) {
      return layer.id;
    }
    else {
      return 0;
    }
  },
  
  getLayerBelowId: function(belowId) {
    var idx;
    
    idx = TegakiLayers.getLayerPosById(belowId);
    
    if (idx < 1) {
      return null;
    }
    
    return Tegaki.layers[idx - 1];
  },
  
  getLayerById: function(id) {
    return Tegaki.layers[TegakiLayers.getLayerPosById(id)];
  },
  
  isSameLayerOrder: function(a, b) {
    var i, al;
    
    if (a.length !== b.length) {
      return false;
    }
    
    for (i = 0; al = a[i]; ++i) {
      if (al.id !== b[i].id) {
        return false;
      }
    }
    
    return true;
  },
  
  addLayer: function(baseLayer = {}) {
    var id, canvas, k, params, layer, afterNode, afterPos,
      aLayerIdBefore, ctx;
    
    if (Tegaki.activeLayer) {
      aLayerIdBefore = Tegaki.activeLayer.id;
      afterPos = TegakiLayers.getLayerPosById(Tegaki.activeLayer.id);
      afterNode = $T.cls('tegaki-layer', Tegaki.layersCnt)[afterPos];
    }
    else {
      afterPos = -1;
      afterNode = null;
    }
    
    if (!afterNode) {
      afterNode = Tegaki.layersCnt.firstElementChild;
    }
    
    canvas = $T.el('canvas');
    canvas.className = 'tegaki-layer';
    canvas.width = Tegaki.baseWidth;
    canvas.height = Tegaki.baseHeight;
    
    id = ++Tegaki.layerCounter;
    
    canvas.id = 'tegaki-canvas-' + id;
    canvas.setAttribute('data-id', id);
    
    params = {
      name: TegakiStrings.layer + ' ' + id,
      visible: true,
      alpha: 1.0,
    };
    
    ctx = canvas.getContext('2d');
    
    layer = {
      id: id,
      canvas: canvas,
      ctx: ctx,
      imageData: ctx.getImageData(0, 0, canvas.width, canvas.height)
    };
    
    for (k in params) {
      if (baseLayer[k] !== undefined) {
        params[k] = baseLayer[k];
      }
      
      layer[k] = params[k];
    }
    
    Tegaki.layers.splice(afterPos + 1, 0, layer);
    
    TegakiUI.updateLayersGridAdd(layer, aLayerIdBefore);
    
    Tegaki.layersCnt.insertBefore(canvas, afterNode.nextElementSibling);
    
    Tegaki.onLayerStackChanged();
    
    return new TegakiHistoryActions.AddLayer(layer, aLayerIdBefore, id);
  },
  
  deleteLayers: function(ids, extraParams) {
    var id, idx, layer, layers, delIndexes, params;
    
    params = {
      aLayerIdBefore: Tegaki.activeLayer ? Tegaki.activeLayer.id : -1,
      aLayerIdAfter: TegakiLayers.getTopFencedLayerId()
    };
    
    layers = [];
    
    delIndexes = [];
    
    for (id of ids) {
      idx = TegakiLayers.getLayerPosById(id);
      layer = Tegaki.layers[idx];
      
      layers.push([idx, layer]);
      
      Tegaki.layersCnt.removeChild(layer.canvas);
      
      delIndexes.push(idx);
      
      TegakiUI.updateLayersGridRemove(id);
      
      TegakiUI.deleteLayerPreviewCtx(layer);
    }
    
    delIndexes = delIndexes.sort($T.sortDescCb);
    
    for (idx of delIndexes) {
      Tegaki.layers.splice(idx, 1);
    }
    
    if (extraParams) {
      Object.assign(params, extraParams);
    }
    
    Tegaki.onLayerStackChanged();
    
    return new TegakiHistoryActions.DeleteLayers(layers, params);
  },
  
  mergeLayers: function(idSet) {
    var canvas, ctx, imageDataAfter, imageDataBefore,
      targetLayer, action, layer, layers, delIds, mergeDown;
    
    layers = [];
    
    for (layer of Tegaki.layers) {
      if (idSet.has(layer.id)) {
        layers.push(layer);
      }
    }
    
    if (layers.length < 1) {
      return;
    }
    
    if (layers.length === 1) {
      targetLayer = TegakiLayers.getLayerBelowId(layers[0].id);
      
      if (!targetLayer) {
        return;
      }
      
      layers.unshift(targetLayer);
      
      mergeDown = true;
    }
    else {
      targetLayer = layers[layers.length - 1];
      
      mergeDown = false;
    }
    
    canvas = $T.el('canvas');
    canvas.width = Tegaki.baseWidth;
    canvas.height = Tegaki.baseHeight;
    
    ctx = canvas.getContext('2d');
    
    imageDataBefore = $T.copyImageData(targetLayer.imageData);
    
    delIds = [];
    
    for (layer of layers) {
      if (layer.id !== targetLayer.id) {
        delIds.push(layer.id);
      }
      
      ctx.globalAlpha = layer.alpha;
      ctx.drawImage(layer.canvas, 0, 0);
    }
    
    $T.clearCtx(targetLayer.ctx);
    
    targetLayer.ctx.drawImage(canvas, 0, 0);
    
    TegakiLayers.syncLayerImageData(targetLayer);
    
    imageDataAfter = $T.copyImageData(targetLayer.imageData);
    
    action = TegakiLayers.deleteLayers(delIds, {
      tgtLayerId: targetLayer.id,
      tgtLayerAlpha: targetLayer.alpha,
      aLayerIdAfter: targetLayer.id,
      imageDataBefore: imageDataBefore,
      imageDataAfter: imageDataAfter,
      mergeDown: mergeDown
    });
    
    TegakiLayers.setLayerAlpha(targetLayer, 1.0);
    
    TegakiUI.updateLayerAlphaOpt();
    
    TegakiUI.updateLayerPreview(targetLayer);
    
    Tegaki.onLayerStackChanged();
    
    return action;
  },
  
  moveLayers: function(idSet, belowPos) {
    var idx, layer,
      historyLayers, updLayers, movedLayers,
      tgtCanvas, updTgtPos;
    
    if (!idSet.size || !Tegaki.layers.length) {
      return;
    }
    
    if (belowPos >= Tegaki.layers.length) {
      tgtCanvas = TegakiLayers.getTopLayer().canvas.nextElementSibling;
    }
    else {
      layer = Tegaki.layers[belowPos];
      tgtCanvas = layer.canvas;
    }
    
    historyLayers = [];
    updLayers = [];
    movedLayers = [];
    
    updTgtPos = belowPos;
    
    idx = 0;
    
    for (layer of Tegaki.layers) {
      if (idSet.has(layer.id)) {
        if (belowPos > 0 && idx <= belowPos) {
          updTgtPos--;
        }
        
        historyLayers.push([idx, layer]);
        movedLayers.push(layer);
      }
      else {
        updLayers.push(layer);
      }
      
      ++idx;
    }
    
    updLayers.splice(updTgtPos, 0, ...movedLayers);
    
    if (TegakiLayers.isSameLayerOrder(updLayers, Tegaki.layers)) {
      return;
    }
    
    Tegaki.layers = updLayers;
    
    for (layer of historyLayers) {
      Tegaki.layersCnt.insertBefore(layer[1].canvas, tgtCanvas);
    }
    
    TegakiUI.updayeLayersGridOrder();
    
    Tegaki.onLayerStackChanged();
    
    return new TegakiHistoryActions.MoveLayers(
      historyLayers, belowPos,
      Tegaki.activeLayer ? Tegaki.activeLayer.id : -1
    );
  },
  
  setLayerVisibility: function(layer, flag) {
    layer.visible = flag;
    
    if (flag) {
      layer.canvas.classList.remove('tegaki-hidden');
    }
    else {
      layer.canvas.classList.add('tegaki-hidden');
    }
    
    Tegaki.onLayerStackChanged();
    
    TegakiUI.updateLayersGridVisibility(layer.id, flag);
  },
  
  setLayerAlpha: function(layer, alpha) {
    layer.alpha = alpha;
    layer.canvas.style.opacity = alpha;
  },
  
  setActiveLayer: function(id) {
    var idx, layer;
    
    if (!id) {
      id = TegakiLayers.getTopLayerId();
      
      if (!id) {
        Tegaki.activeLayer = null;
        return;
      }
    }
    
    idx = TegakiLayers.getLayerPosById(id);
    
    if (idx < 0) {
      return;
    }
    
    layer = Tegaki.layers[idx];
    
    if (Tegaki.activeLayer) {
      Tegaki.copyContextState(Tegaki.activeLayer.ctx, layer.ctx);
    }
    
    Tegaki.activeLayer = layer;
    
    TegakiLayers.selectedLayersClear();
    TegakiLayers.selectedLayersAdd(id);
    
    TegakiUI.updateLayersGridActive(id);
    TegakiUI.updateLayerAlphaOpt();
    
    Tegaki.onLayerStackChanged();
  },
  
  syncLayerImageData(layer, imageData = null) {
    if (imageData) {
      layer.imageData = $T.copyImageData(imageData);
    }
    else {
      layer.imageData = layer.ctx.getImageData(
        0, 0, Tegaki.baseWidth, Tegaki.baseHeight
      );
    }
  },
  
  selectedLayersHas: function(id) {
    return Tegaki.selectedLayers.has(+id);
  },
  
  selectedLayersClear: function() {
    Tegaki.selectedLayers.clear();
    TegakiUI.updateLayerAlphaOpt();
    TegakiUI.updateLayersGridSelectedClear();
  },
  
  selectedLayersAdd: function(id) {
    Tegaki.selectedLayers.add(+id);
    TegakiUI.updateLayerAlphaOpt();
    TegakiUI.updateLayersGridSelectedSet(id, true);
  },
  
  selectedLayersRemove: function(id) {
    Tegaki.selectedLayers.delete(+id);
    TegakiUI.updateLayerAlphaOpt();
    TegakiUI.updateLayersGridSelectedSet(id, false);
  },
  
  selectedLayersToggle: function(id) {
    if (TegakiLayers.selectedLayersHas(id)) {
      TegakiLayers.selectedLayersRemove(id);
    }
    else {
      TegakiLayers.selectedLayersAdd(id);
    }
  }
};
var Tegaki = {
  VERSION: '0.9.1',
  
  startTimeStamp: 0,
  
  bg: null,
  canvas: null,
  ctx: null,
  layers: [],
  
  layersCnt: null,
  canvasCnt: null,
  
  ghostBuffer: null,
  blendBuffer: null,
  ghostBuffer32: null,
  blendBuffer32: null,
  
  activeLayer: null,
  
  layerCounter: 0,
  selectedLayers: new Set(),
  
  activePointerId: 0,
  activePointerIsPen: false,
  
  isPainting: false,
  
  offsetX: 0,
  offsetY: 0,
  
  zoomLevel: 0,
  zoomFactor: 1.0,
  zoomFactorList: [0.5, 1.0, 2.0, 4.0, 8.0, 16.0],
  zoomBaseLevel: 1,
  
  hasCustomCanvas: false,
  
  TWOPI: 2 * Math.PI,
  
  toolList: [
    TegakiPencil,
    TegakiPen,
    TegakiAirbrush,
    TegakiBucket,
    TegakiTone,
    TegakiPipette,
    TegakiBlur,
    TegakiEraser
  ],
  
  tools: {},
  
  tool: null,
  
  colorPaletteId: 0,
  
  toolColor: '#000000',
  defaultTool: 'pencil',
  
  bgColor: '#ffffff',
  maxSize: 64,
  maxLayers: 25,
  baseWidth: 0,
  baseHeight: 0,
  
  replayRecorder: null,
  replayViewer: null,
  
  onDoneCb: null,
  onCancelCb: null,
  
  replayMode: false,
  
  saveReplay: false,
  
  open: function(opts = {}) {
    var self = Tegaki;
    
    if (self.bg) {
      if (self.replayMode !== (opts.replayMode ? true : false)) {
        self.destroy();
      }
      else {
        self.resume();
        return;
      }
    }
    
    self.startTimeStamp = Date.now();
    
    if (opts.bgColor) {
      self.bgColor = opts.bgColor;
    }
    
    self.hasCustomCanvas = false;
    
    self.saveReplay = !!opts.saveReplay;
    self.replayMode = !!opts.replayMode;
    
    self.onDoneCb = opts.onDone;
    self.onCancelCb = opts.onCancel;
    
    self.baseWidth = opts.width || 0;
    self.baseHeight = opts.height || 0;
    
    self.createTools();
    
    self.initKeybinds();
    
    [self.bg, self.canvasCnt, self.layersCnt] = TegakiUI.buildUI();
    
    document.body.appendChild(self.bg);
    document.body.classList.add('tegaki-backdrop');
    
    if (!self.replayMode) {
      self.init();
      
      self.setTool(self.defaultTool);
      
      if (self.saveReplay) {
        self.replayRecorder = new TegakiReplayRecorder();
        self.replayRecorder.start();
      }
    }
    else {
      TegakiUI.setReplayMode(true);
      
      self.replayViewer = new TegakiReplayViewer();
      
      if (opts.replayURL) {
        self.loadReplayFromURL(opts.replayURL);
      }
    }
  },
  
  init: function() {
    var self = Tegaki;
    
    self.createCanvas();
    
    self.centerLayersCnt();
    
    self.createBuffers();
    
    self.updatePosOffset();
    
    self.resetLayers();
    
    self.bindGlobalEvents();
    
    TegakiCursor.init(self.baseWidth, self.baseHeight);
    
    TegakiUI.updateUndoRedo(0, 0);
    TegakiUI.updateZoomLevel();
  },
  
  initFromReplay: function() {
    var self, r;
    
    self = Tegaki;
    r = self.replayViewer;
    
    self.initToolsFromReplay();
    
    self.baseWidth = r.canvasWidth;
    self.baseHeight = r.canvasHeight;
    self.bgColor = $T.RgbToHex(...r.bgColor);
    
    self.toolColor = $T.RgbToHex(...r.toolColor);
  },
  
  initToolsFromReplay: function() {
    var self, r, name, tool, rTool, prop, props;
    
    self = Tegaki;
    r = self.replayViewer;
    
    for (name in self.tools) {
      tool = self.tools[name];
      
      if (tool.id === r.toolId) {
        self.defaultTool = name;
      }
      
      rTool = r.toolMap[tool.id];
      
      props = ['step', 'size', 'alpha', 'flow', 'tipId'];
      
      for (prop of props) {
        if (rTool[prop] !== undefined) {
          tool[prop] = rTool[prop];
        }
      }
      
      props = [
        'sizeDynamicsEnabled', 'alphaDynamicsEnabled', 'flowDynamicsEnabled',
        'usePreserveAlpha'
      ];
      
      for (prop of props) {
        if (rTool[prop] !== undefined) {
          tool[prop] = !!rTool[prop];
        }
      }
    }
  },
  
  resetLayers: function() {
    var i, len;
    
    if (Tegaki.layers.length) {
      for (i = 0, len = Tegaki.layers.length; i < len; ++i) {
        Tegaki.layersCnt.removeChild(Tegaki.layers[i].canvas);
      }
      
      Tegaki.layers = [];
      Tegaki.layerCounter = 0;
      
      TegakiUI.updateLayersGridClear();
    }
    
    TegakiLayers.addLayer();
    TegakiLayers.setActiveLayer(0);
  },
  
  createCanvas: function() {
    var canvas, self = Tegaki;
    
    canvas = $T.el('canvas');
    canvas.id = 'tegaki-canvas';
    canvas.width = self.baseWidth;
    canvas.height = self.baseHeight;
    
    self.canvas = canvas;
    
    self.ctx = canvas.getContext('2d');
    self.ctx.fillStyle = self.bgColor;
    self.ctx.fillRect(0, 0, self.baseWidth, self.baseHeight);
    
    self.layersCnt.appendChild(canvas);
  },
  
  createTools: function() {
    var klass, tool;
    
    for (klass of Tegaki.toolList) {
      tool = new klass();
      Tegaki.tools[tool.name] = tool;
    }
  },
  
  bindGlobalEvents: function() {
    var self = Tegaki;
    
    if (!self.replayMode) {
      $T.on(self.canvasCnt, 'pointermove', self.onPointerMove);
      $T.on(self.canvasCnt, 'pointerdown', self.onPointerDown);
      $T.on(document, 'pointerup', self.onPointerUp);
      $T.on(document, 'pointercancel', self.onPointerUp);
      
      $T.on(document, 'keydown', TegakiKeybinds.resolve);
      
      $T.on(window, 'beforeunload', Tegaki.onTabClose);
    }
    else {
      $T.on(document, 'visibilitychange', Tegaki.onVisibilityChange);
    }
    
    $T.on(self.bg, 'contextmenu', self.onDummy);
    $T.on(window, 'resize', self.updatePosOffset);
    $T.on(window, 'scroll', self.updatePosOffset);
  },
  
  unBindGlobalEvents: function() {
    var self = Tegaki;
    
    if (!self.replayMode) {
      $T.off(self.canvasCnt, 'pointermove', self.onPointerMove);
      $T.off(self.canvasCnt, 'pointerdown', self.onPointerDown);
      $T.off(document, 'pointerup', self.onPointerUp);
      $T.off(document, 'pointercancel', self.onPointerUp);
      
      $T.off(document, 'keydown', TegakiKeybinds.resolve);
      
      $T.off(window, 'beforeunload', Tegaki.onTabClose);
    }
    else {
      $T.off(document, 'visibilitychange', Tegaki.onVisibilityChange);
    }
    
    $T.off(self.bg, 'contextmenu', self.onDummy);
    $T.off(window, 'resize', self.updatePosOffset);
    $T.off(window, 'scroll', self.updatePosOffset);
  },
  
  createBuffers() {
    Tegaki.ghostBuffer = new ImageData(Tegaki.baseWidth, Tegaki.baseHeight);
    Tegaki.blendBuffer = new ImageData(Tegaki.baseWidth, Tegaki.baseHeight);
    Tegaki.ghostBuffer32 = new Uint32Array(Tegaki.ghostBuffer.data.buffer);
    Tegaki.blendBuffer32 = new Uint32Array(Tegaki.blendBuffer.data.buffer);
  },
  
  clearBuffers() {
    Tegaki.ghostBuffer32.fill(0);
    Tegaki.blendBuffer32.fill(0);
  },
  
  destroyBuffers() {
    Tegaki.ghostBuffer = null;
    Tegaki.blendBuffer = null;
    Tegaki.ghostBuffer32 = null;
    Tegaki.blendBuffer32 = null;
  },
  
  disableSmoothing: function(ctx) {
    ctx.mozImageSmoothingEnabled = false;
    ctx.webkitImageSmoothingEnabled = false;
    ctx.msImageSmoothingEnabled = false;
    ctx.imageSmoothingEnabled = false;
  },
  
  centerLayersCnt: function() {
    var style = Tegaki.layersCnt.style;
    
    style.width = Tegaki.baseWidth + 'px';
    style.height = Tegaki.baseHeight + 'px';
  },
  
  onTabClose: function(e) {
    e.preventDefault();
    e.returnValue = '';
  },
  
  onVisibilityChange: function(e) {
    if (!Tegaki.replayMode) {
      return;
    }
    
    if (document.visibilityState === 'visible') {
      if (Tegaki.replayViewer.autoPaused) {
        Tegaki.replayViewer.play();
      }
    }
    else {
      if (Tegaki.replayViewer.playing) {
        Tegaki.replayViewer.autoPause();
      }
    }
  },
  
  initKeybinds: function() {
    var cls, tool;
    
    if (Tegaki.replayMode) {
      return;
    }
    
    TegakiKeybinds.bind('ctrl+z', TegakiHistory, 'undo', 'undo', 'Ctrl+Z');
    TegakiKeybinds.bind('ctrl+y', TegakiHistory, 'redo', 'redo', 'Ctrl+Y');
    
    TegakiKeybinds.bind('+', Tegaki, 'setToolSizeUp', 'toolSize', 'Numpad +/-');
    TegakiKeybinds.bind('-', Tegaki, 'setToolSizeDown');
    
    for (tool in Tegaki.tools) {
      cls = Tegaki.tools[tool];
      
      if (cls.keybind) {
        TegakiKeybinds.bind(cls.keybind, cls, 'set');
      }
    }
  },
  
  getCursorPos: function(e, axis) {
    if (axis === 0) {
      return 0 | ((
        e.clientX
          + window.pageXOffset
          + Tegaki.canvasCnt.scrollLeft
          - Tegaki.offsetX
        ) / Tegaki.zoomFactor);
    }
    else {
      return 0 | ((
        e.clientY
          + window.pageYOffset
          + Tegaki.canvasCnt.scrollTop
          - Tegaki.offsetY
        ) / Tegaki.zoomFactor);
    }
  },
  
  resume: function() {
    if (Tegaki.saveReplay) {
      Tegaki.replayRecorder.start();
    }
    
    Tegaki.bg.classList.remove('tegaki-hidden');
    document.body.classList.add('tegaki-backdrop');
    Tegaki.setZoom(0);
    Tegaki.centerLayersCnt();
    Tegaki.updatePosOffset();
    Tegaki.bindGlobalEvents();
  },
  
  hide: function() {
    if (Tegaki.saveReplay) {
      Tegaki.replayRecorder.stop();
    }
    
    Tegaki.bg.classList.add('tegaki-hidden');
    document.body.classList.remove('tegaki-backdrop');
    Tegaki.unBindGlobalEvents();
  },
  
  destroy: function() {
    Tegaki.unBindGlobalEvents();
    
    TegakiKeybinds.clear();
    
    TegakiHistory.clear();
    
    Tegaki.bg.parentNode.removeChild(Tegaki.bg);
    
    document.body.classList.remove('tegaki-backdrop');
    
    Tegaki.startTimeStamp = 0;
    
    Tegaki.bg = null;
    Tegaki.canvasCnt = null;
    Tegaki.layersCnt = null;
    Tegaki.canvas = null;
    Tegaki.ctx = null;
    Tegaki.layers = [];
    Tegaki.layerCounter = 0;
    Tegaki.zoomLevel = 0;
    Tegaki.zoomFactor = 1.0;
    Tegaki.activeLayer = null;
    
    Tegaki.tool = null;
    
    TegakiCursor.destroy();
    
    Tegaki.replayRecorder = null;
    Tegaki.replayViewer = null;
    
    Tegaki.destroyBuffers();
  },
  
  flatten: function(ctx) {
    var i, layer, canvas, len;
    
    if (!ctx) {
      canvas = $T.el('canvas');
      ctx = canvas.getContext('2d');
    }
    else {
      canvas = ctx.canvas;
    }
    
    canvas.width = Tegaki.canvas.width;
    canvas.height = Tegaki.canvas.height;
    
    ctx.drawImage(Tegaki.canvas, 0, 0);
    
    for (i = 0, len = Tegaki.layers.length; i < len; ++i) {
      layer = Tegaki.layers[i];
      
      if (!layer.visible) {
        continue;
      }
      
      ctx.globalAlpha = layer.alpha;
      ctx.drawImage(layer.canvas, 0, 0);
    }
    
    return canvas;
  },
  
  onReplayLoaded: function() {
    TegakiUI.clearMsg();
    Tegaki.initFromReplay();
    Tegaki.init();
    Tegaki.setTool(Tegaki.defaultTool);
    TegakiUI.updateReplayControls();
    TegakiUI.updateReplayTime(true);
    TegakiUI.enableReplayControls(true);
    Tegaki.replayViewer.play();
  },
  
  onReplayGaplessClick: function() {
    Tegaki.replayViewer.toggleGapless();
    TegakiUI.updateReplayGapless();
  },
  
  onReplayPlayPauseClick: function() {
    Tegaki.replayViewer.togglePlayPause();
  },
  
  onReplayRewindClick: function() {
    Tegaki.replayViewer.rewind();
  },
  
  onReplaySlowDownClick: function() {
    Tegaki.replayViewer.slowDown();
    TegakiUI.updateReplaySpeed();
  },
  
  onReplaySpeedUpClick: function() {
    Tegaki.replayViewer.speedUp();
    TegakiUI.updateReplaySpeed();
  },
  
  onReplayTimeChanged: function() {
    TegakiUI.updateReplayTime();
  },
  
  onReplayPlayPauseChanged: function() {
    TegakiUI.updateReplayPlayPause();
  },
  
  onReplayReset: function() {
    Tegaki.initFromReplay();
    Tegaki.setTool(Tegaki.defaultTool);
    Tegaki.resizeCanvas(Tegaki.baseWidth, Tegaki.baseHeight);
    TegakiUI.updateReplayControls();
    TegakiUI.updateReplayTime();
  },
  
  onMainColorClick: function(e) {
    var el;
    e.preventDefault();
    el = $T.id('tegaki-colorpicker');
    el.click();
  },
  
  onPaletteColorClick: function(e) {
    if (e.button === 2) {
      this.style.backgroundColor = Tegaki.toolColor;
      this.setAttribute('data-color', Tegaki.toolColor);
    }
    else if (e.button === 0) {
      Tegaki.setToolColor(this.getAttribute('data-color'));
    }
  },
  
  onColorPicked: function(e) {
    $T.id('tegaki-color').style.backgroundColor = this.value;
    Tegaki.setToolColor(this.value);
  },
  
  onSwitchPaletteClick: function(e) {
    var id;
    
    if (e.target.hasAttribute('data-prev')) {
      id = Tegaki.colorPaletteId - 1;
    }
    else {
      id = Tegaki.colorPaletteId + 1;
    }
    
    Tegaki.setColorPalette(id);
  },
  
  setColorPalette: function(id) {
    if (id < 0 || id >= TegakiColorPalettes.length) {
      return;
    }
    
    Tegaki.colorPaletteId = id;
    TegakiUI.updateColorPalette();
  },
  
  setToolSizeUp: function() {
    Tegaki.setToolSize(Tegaki.tool.size + 1);
  },
  
  setToolSizeDown: function() {
    Tegaki.setToolSize(Tegaki.tool.size - 1);
  },
  
  setToolSize: function(size) {
    if (size > 0 && size <= Tegaki.maxSize) {
      Tegaki.tool.setSize(size);
      Tegaki.updateCursorStatus();
      Tegaki.recordEvent(TegakiEventSetToolSize, performance.now(), size);
      TegakiUI.updateToolSize();
    }
  },
  
  setToolAlpha: function(alpha) {
    alpha = Math.fround(alpha);
    
    if (alpha >= 0.0 && alpha <= 1.0) {
      Tegaki.tool.setAlpha(alpha);
      Tegaki.recordEvent(TegakiEventSetToolAlpha, performance.now(), alpha);
      TegakiUI.updateToolAlpha();
    }
  },
  
  setToolFlow: function(flow) {
    flow = Math.fround(flow);
    
    if (flow >= 0.0 && flow <= 1.0) {
      Tegaki.tool.setFlow(flow);
      Tegaki.recordEvent(TegakiEventSetToolFlow, performance.now(), flow);
      TegakiUI.updateToolFlow();
    }
  },
  
  setToolColor: function(color) {
    Tegaki.toolColor = color;
    $T.id('tegaki-color').style.backgroundColor = color;
    $T.id('tegaki-colorpicker').value = color;
    Tegaki.tool.setColor(color);
    Tegaki.recordEvent(TegakiEventSetColor, performance.now(), Tegaki.tool.rgb);
  },
  
  setToolColorRGB: function(r, g, b) {
    Tegaki.setToolColor($T.RgbToHex(r, g, b));
  },
  
  setTool: function(tool) {
    Tegaki.tools[tool].set();
  },
  
  setToolById: function(id) {
    var tool;
    
    for (tool in Tegaki.tools) {
      if (Tegaki.tools[tool].id === id) {
        Tegaki.setTool(tool);
        return;
      }
    }
  },
  
  setZoom: function(level) {
    var idx;
    
    idx = level + Tegaki.zoomBaseLevel;
    
    if (idx >= Tegaki.zoomFactorList.length || idx < 0 || !Tegaki.canvas) {
      return;
    }
    
    Tegaki.zoomLevel = level;
    Tegaki.zoomFactor = Tegaki.zoomFactorList[idx];
    
    TegakiUI.updateZoomLevel();
    
    Tegaki.layersCnt.style.width = Math.ceil(Tegaki.baseWidth * Tegaki.zoomFactor) + 'px';
    Tegaki.layersCnt.style.height = Math.ceil(Tegaki.baseHeight * Tegaki.zoomFactor) + 'px';
    
    if (level < 0) {
      Tegaki.layersCnt.classList.add('tegaki-smooth-layers');
    }
    else {
      Tegaki.layersCnt.classList.remove('tegaki-smooth-layers');
    }
    
    Tegaki.updatePosOffset();
  },
  
  onZoomChange: function() {
    if (this.hasAttribute('data-in')) {
      Tegaki.setZoom(Tegaki.zoomLevel + 1);
    }
    else {
      Tegaki.setZoom(Tegaki.zoomLevel - 1);
    }
  },
  
  onNewClick: function() {
    var width, height, tmp, self = Tegaki;
    
    width = prompt(TegakiStrings.promptWidth, self.canvas.width);
    
    if (!width) { return; }
    
    height = prompt(TegakiStrings.promptHeight, self.canvas.height);
    
    if (!height) { return; }
    
    width = +width;
    height = +height;
    
    if (width < 1 || height < 1) {
      TegakiUI.printMsg(TegakiStrings.badDimensions);
      return;
    }
    
    tmp = {};
    self.copyContextState(self.activeLayer.ctx, tmp);
    self.resizeCanvas(width, height);
    self.copyContextState(tmp, self.activeLayer.ctx);
    
    self.setZoom(0);
    TegakiHistory.clear();
    
    TegakiUI.updateLayerPreviewSize();
    
    self.startTimeStamp = Date.now();
    
    if (self.saveReplay) {
      self.createTools();
      self.setTool(self.defaultTool);
      self.replayRecorder = new TegakiReplayRecorder();
      self.replayRecorder.start();
    }
  },
  
  onOpenClick: function() {
    var el, tainted;
    
    tainted = TegakiHistory.undoStack[0] || TegakiHistory.redoStack[0];
    
    if (tainted || Tegaki.saveReplay) {
      if (!confirm(TegakiStrings.confirmChangeCanvas)) {
        return;
      }
    }
    
    el = $T.id('tegaki-filepicker');
    el.click();
  },
  
  loadReplayFromFile: function() {
    Tegaki.replayViewer.debugLoadLocal();
  },
  
  loadReplayFromURL: function(url) {
    TegakiUI.printMsg(TegakiStrings.loadingReplay, 0);
    Tegaki.replayViewer.loadFromURL(url);
  },
  
  onExportClick: function() {
    Tegaki.flatten().toBlob(function(b) {
      var el = $T.el('a');
      el.className = 'tegaki-hidden';
      el.download = $T.generateFilename() + '.png';
      el.href = URL.createObjectURL(b);
      Tegaki.bg.appendChild(el);
      el.click();
      Tegaki.bg.removeChild(el);
    }, 'image/png');
  },
  
  onUndoClick: function() {
    TegakiHistory.undo();
  },
  
  onRedoClick: function() {
    TegakiHistory.redo();
  },
  
  onHistoryChange: function(undoSize, redoSize, type = 0) {
    TegakiUI.updateUndoRedo(undoSize, redoSize);
    
    if (type === -1) {
      Tegaki.recordEvent(TegakiEventUndo, performance.now());
    }
    else if (type === 1) {
      Tegaki.recordEvent(TegakiEventRedo, performance.now());
    }
  },
  
  onDoneClick: function() {
    Tegaki.hide();
    Tegaki.onDoneCb();
  },
  
  onCancelClick: function() {
    if (!confirm(TegakiStrings.confirmCancel)) {
      return;
    }
    
    Tegaki.destroy();
    Tegaki.onCancelCb();
  },
  
  onCloseViewerClick: function() {
    Tegaki.replayViewer.destroy();
    Tegaki.destroy();
  },
  
  onToolSizeChange: function() {
    var val = +this.value;
    
    if (val < 1) {
      val = 1;
    }
    else if (val > Tegaki.maxSize) {
      val = Tegaki.maxSize;
    }
    
    Tegaki.setToolSize(val);
  },
  
  onToolAlphaChange: function(e) {
    var val = +this.value;
    
    val = val / 100;
    
    if (val < 0.0) {
      val = 0.0;
    }
    else if (val > 1.0) {
      val = 1.0;
    }
    
    Tegaki.setToolAlpha(val);
  },
  
  onToolFlowChange: function(e) {
    var val = +this.value;
    
    val = val / 100;
    
    if (val < 0.0) {
      val = 0.0;
    }
    else if (val > 1.0) {
      val = 1.0;
    }
    
    Tegaki.setToolFlow(val);
  },
  
  onToolPressureSizeClick: function(e) {
    if (!Tegaki.tool.useSizeDynamics) {
      return;
    }
    
    Tegaki.setToolSizeDynamics(!Tegaki.tool.sizeDynamicsEnabled);
  },
  
  setToolSizeDynamics: function(flag) {
    Tegaki.tool.setSizeDynamics(flag);
    TegakiUI.updateToolDynamics();
    Tegaki.recordEvent(TegakiEventSetToolSizeDynamics, performance.now(), +flag);
  },
  
  onToolPressureAlphaClick: function(e) {
    if (!Tegaki.tool.useAlphaDynamics) {
      return;
    }
    
    Tegaki.setToolAlphaDynamics(!Tegaki.tool.alphaDynamicsEnabled);
  },
  
  setToolAlphaDynamics: function(flag) {
    Tegaki.tool.setAlphaDynamics(flag);
    TegakiUI.updateToolDynamics();
    Tegaki.recordEvent(TegakiEventSetToolAlphaDynamics, performance.now(), +flag);
  },
  
  onToolPressureFlowClick: function(e) {
    if (!Tegaki.tool.useFlowDynamics) {
      return;
    }
    
    Tegaki.setToolFlowDynamics(!Tegaki.tool.flowDynamicsEnabled);
  },
  
  setToolFlowDynamics: function(flag) {
    Tegaki.tool.setFlowDynamics(flag);
    TegakiUI.updateToolDynamics();
    Tegaki.recordEvent(TegakiEventSetToolFlowDynamics, performance.now(), +flag);
  },
  
  onToolPreserveAlphaClick: function(e) {
    if (!Tegaki.tool.usePreserveAlpha) {
      return;
    }
    
    Tegaki.setToolPreserveAlpha(!Tegaki.tool.preserveAlphaEnabled);
  },
  
  setToolPreserveAlpha: function(flag) {
    Tegaki.tool.setPreserveAlpha(flag);
    TegakiUI.updateToolPreserveAlpha();
    Tegaki.recordEvent(TegakiEventPreserveAlpha, performance.now(), +flag);
  },
  
  onToolTipClick: function(e) {
    var tipId = +e.target.getAttribute('data-id');
    
    if (tipId !== Tegaki.tool.tipId) {
      Tegaki.setToolTip(tipId);
    }
  },
  
  setToolTip: function(id) {
    Tegaki.tool.setTip(id);
    TegakiUI.updateToolShape();
    Tegaki.recordEvent(TegakiEventSetToolTip, performance.now(), id);
  },
  
  onLayerSelectorClick: function(e) {
    var id = +this.getAttribute('data-id');
    
    if (!id || e.target.classList.contains('tegaki-ui-cb')) {
      return;
    }
    
    if (e.ctrlKey) {
      Tegaki.toggleSelectedLayer(id);
    }
    else {
      Tegaki.setActiveLayer(id);
    }
  },
  
  toggleSelectedLayer: function(id) {
    TegakiLayers.selectedLayersToggle(id);
    Tegaki.recordEvent(TegakiEventToggleLayerSelection, performance.now(), id);
  },
  
  setActiveLayer: function(id) {
    TegakiLayers.setActiveLayer(id);
    Tegaki.recordEvent(TegakiEventSetActiveLayer, performance.now(), id);
  },
  
  onLayerAlphaDragStart: function(e) {
    TegakiUI.setupDragLabel(e, Tegaki.onLayerAlphaDragMove);
  },
  
  onLayerAlphaDragMove: function(delta) {
    var val;
    
    if (!delta) {
      return;
    }
    
    val = Tegaki.activeLayer.alpha + delta / 100 ;
    
    if (val < 0.0) {
      val = 0.0;
    }
    else if (val > 1.0) {
      val = 1.0;
    }
    
    Tegaki.setSelectedLayersAlpha(val);
  },
  
  onLayerAlphaChange: function() {
    var val = +this.value;
    
    val = val / 100;
    
    if (val < 0.0) {
      val = 0.0;
    }
    else if (val > 1.0) {
      val = 1.0;
    }
    
    Tegaki.setSelectedLayersAlpha(val);
  },
  
  setSelectedLayersAlpha: function(alpha) {
    var layer, id, layerAlphas;
    
    alpha = Math.fround(alpha);
    
    if (alpha >= 0.0 && alpha <= 1.0 && Tegaki.selectedLayers.size > 0) {
      layerAlphas = [];
      
      for (id of Tegaki.selectedLayers) {
        if (layer = TegakiLayers.getLayerById(id)) {
          layerAlphas.push([layer.id, layer.alpha]);
          TegakiLayers.setLayerAlpha(layer, alpha);
        }
      }
      
      TegakiUI.updateLayerAlphaOpt();
      
      TegakiHistory.push(new TegakiHistoryActions.SetLayersAlpha(layerAlphas, alpha));
      
      Tegaki.recordEvent(TegakiEventSetSelectedLayersAlpha, performance.now(), alpha);
    }
  },
  
  onLayerNameChangeClick: function(e) {
    var id, name, layer;
    
    id = +this.getAttribute('data-id');
    
    layer = TegakiLayers.getLayerById(id);
    
    if (!layer) {
      return;
    }
    
    if (name = prompt(undefined, layer.name)) {
      Tegaki.setLayerName(id, name);
    }
  },
  
  setLayerName: function(id, name) {
    var oldName, layer;
    
    name = name.trim().slice(0, 25);
    
    layer = TegakiLayers.getLayerById(id);
    
    if (!layer || !name || name === layer.name) {
      return;
    }
    
    oldName = layer.name;
    
    layer.name = name;
    
    TegakiUI.updateLayerName(layer);
    
    TegakiHistory.push(new TegakiHistoryActions.SetLayerName(id, oldName, name));
    
    Tegaki.recordEvent(TegakiEventHistoryDummy, performance.now());
  },
  
  onLayerAddClick: function() {
    Tegaki.addLayer();
  },
  
  addLayer: function() {
    var action;
    
    if (Tegaki.layers.length >= Tegaki.maxLayers) {
      TegakiUI.printMsg(TegakiStrings.tooManyLayers);
      return;
    }
    
    TegakiHistory.push(action = TegakiLayers.addLayer());
    TegakiLayers.setActiveLayer(action.aLayerIdAfter);
    Tegaki.recordEvent(TegakiEventAddLayer, performance.now());
  },
  
  onLayerDeleteClick: function() {
    Tegaki.deleteSelectedLayers();
  },
  
  deleteSelectedLayers: function() {
    var action, layerSet;
    
    layerSet = Tegaki.selectedLayers;
    
    if (layerSet.size === Tegaki.layers.length) {
      return;
    }
    
    if (!layerSet.size || Tegaki.layers.length < 2) {
      return;
    }
    
    TegakiHistory.push(action = TegakiLayers.deleteLayers(layerSet));
    TegakiLayers.selectedLayersClear();
    TegakiLayers.setActiveLayer(action.aLayerIdAfter);
    Tegaki.recordEvent(TegakiEventDeleteLayers, performance.now());
  },
  
  onLayerToggleVisibilityClick: function() {
    Tegaki.toggleLayerVisibility(+this.getAttribute('data-id'));
  },
  
  toggleLayerVisibility: function(id) {
    var layer = TegakiLayers.getLayerById(id);
    TegakiLayers.setLayerVisibility(layer, !layer.visible);
    Tegaki.recordEvent(TegakiEventToggleLayerVisibility, performance.now(), id);
  },
  
  onMergeLayersClick: function() {
    Tegaki.mergeSelectedLayers();
  },
  
  mergeSelectedLayers: function() {
    var action;
    
    if (Tegaki.selectedLayers.size) {
      if (action = TegakiLayers.mergeLayers(Tegaki.selectedLayers)) {
        TegakiHistory.push(action);
        TegakiLayers.setActiveLayer(action.aLayerIdAfter);
        Tegaki.recordEvent(TegakiEventMergeLayers, performance.now());
      }
    }
  },
  
  onMoveLayerClick: function(e) {
    var belowPos, up;
    
    if (!Tegaki.selectedLayers.size) {
      return;
    }
    
    up = e.target.hasAttribute('data-up');
    
    belowPos = TegakiLayers.getSelectedEdgeLayerPos(up);
    
    if (belowPos < 0) {
      return;
    }
    
    if (up) {
      belowPos += 2;
    }
    else if (belowPos >= 1) {
      belowPos--;
    }
    
    Tegaki.moveSelectedLayers(belowPos);
  },
  
  moveSelectedLayers: function(belowPos) {
    TegakiHistory.push(TegakiLayers.moveLayers(Tegaki.selectedLayers, belowPos));
    Tegaki.recordEvent(TegakiEventMoveLayers, performance.now(), belowPos);
  },
  
  onToolClick: function() {
    Tegaki.setTool(this.getAttribute('data-tool'));
  },
  
  onToolChanged: function(tool) {
    var el;
    
    Tegaki.tool = tool;
    
    if (el = $T.cls('tegaki-tool-active')[0]) {
      el.classList.remove('tegaki-tool-active');
    }
    
    $T.id('tegaki-tool-btn-' + tool.name).classList.add('tegaki-tool-active');
    
    Tegaki.recordEvent(TegakiEventSetTool, performance.now(), Tegaki.tool.id);
    
    TegakiUI.onToolChanged();
    Tegaki.updateCursorStatus();
  },
  
  onLayerStackChanged: function() {
    TegakiCursor.invalidateCache();
  },
  
  onOpenFileSelected: function() {
    var img;
    
    if (this.files && this.files[0]) {
      img = new Image();
      img.onload = Tegaki.onOpenImageLoaded;
      img.onerror = Tegaki.onOpenImageError;
      
      img.src = URL.createObjectURL(this.files[0]);
    }
  },
  
  onOpenImageLoaded: function() {
    var tmp = {}, self = Tegaki;
    
    self.hasCustomCanvas = true;
    
    self.copyContextState(self.activeLayer.ctx, tmp);
    self.resizeCanvas(this.naturalWidth, this.naturalHeight);
    self.activeLayer.ctx.drawImage(this, 0, 0);
    TegakiLayers.syncLayerImageData(self.activeLayer);
    self.copyContextState(tmp, self.activeLayer.ctx);
    
    self.setZoom(0);
    
    TegakiHistory.clear();
    
    TegakiUI.updateLayerPreviewSize(true);
    
    self.startTimeStamp = Date.now();
    
    if (self.saveReplay) {
      self.replayRecorder.stop();
      self.replayRecorder = null;
      self.saveReplay = false;
      TegakiUI.setRecordingStatus(false);
    }
  },
  
  onOpenImageError: function() {
    TegakiUI.printMsg(TegakiStrings.errorLoadImage);
  },
  
  resizeCanvas: function(width, height) {
    Tegaki.baseWidth = width;
    Tegaki.baseHeight = height;
    
    Tegaki.createBuffers();
    
    Tegaki.canvas.width = width;
    Tegaki.canvas.height = height;
    
    TegakiCursor.updateCanvasSize(width, height);
    
    Tegaki.ctx.fillStyle = Tegaki.bgColor;
    Tegaki.ctx.fillRect(0, 0, width, height);
    
    Tegaki.activeLayer = null;
    
    Tegaki.resetLayers();
    
    Tegaki.centerLayersCnt();
    Tegaki.updatePosOffset();
  },
  
  copyContextState: function(src, dest) {
    var i, p, props = [
      'lineCap', 'lineJoin', 'strokeStyle', 'fillStyle', 'globalAlpha',
      'lineWidth', 'globalCompositeOperation'
    ];
    
    for (i = 0; p = props[i]; ++i) {
      dest[p] = src[p];
    }
  },
  
  updateCursorStatus: function() {
    if (!Tegaki.tool.noCursor && Tegaki.tool.size > 1) {
      Tegaki.cursor = true;
      TegakiCursor.generate(Tegaki.tool.size);
    }
    else {
      Tegaki.cursor = false;
      $T.clearCtx(TegakiCursor.cursorCtx);
    }
  },
  
  updatePosOffset: function() {
    var aabb = Tegaki.canvas.getBoundingClientRect();
    
    Tegaki.offsetX = aabb.left + window.pageXOffset
      + Tegaki.canvasCnt.scrollLeft + Tegaki.layersCnt.scrollLeft;
    Tegaki.offsetY = aabb.top + window.pageYOffset
      + Tegaki.canvasCnt.scrollTop + Tegaki.layersCnt.scrollTop;
  },
  
  isScrollbarClick: function(e) {
    var sbwh, scbv;
    
    sbwh = Tegaki.canvasCnt.offsetWidth - Tegaki.canvasCnt.clientWidth;
    scbv = Tegaki.canvasCnt.offsetHeight - Tegaki.canvasCnt.clientHeight;

    if (sbwh > 0
      && e.clientX >= Tegaki.canvasCnt.offsetLeft + Tegaki.canvasCnt.clientWidth
      && e.clientX <= Tegaki.canvasCnt.offsetLeft + Tegaki.canvasCnt.clientWidth
        + sbwh) {
      return true;
    }
    
    if (scbv > 0
      && e.clientY >= Tegaki.canvasCnt.offsetTop + Tegaki.canvasCnt.clientHeight
      && e.clientY <= Tegaki.canvasCnt.offsetTop + Tegaki.canvasCnt.clientHeight
        + sbwh) {
      return true;
    }
    
    return false;
  },
  
  onPointerMove: function(e) {
    var events, x, y, tool, ts, p;
    
    if (e.mozInputSource !== undefined) {
      // Firefox thing where mouse events fire for no reason when the pointer is a pen
      if (Tegaki.activePointerIsPen && e.pointerType === 'mouse') {
        return;
      }
    }
    else {
      // Webkit thing where a pointermove event is fired at pointerdown location after a pointerup
      if (Tegaki.activePointerId !== e.pointerId) {
        Tegaki.activePointerId = e.pointerId;
        return;
      }
    }
    
    if (Tegaki.isPainting) {
      tool = Tegaki.tool;
      
      if (Tegaki.activePointerIsPen && e.getCoalescedEvents) {
        events = e.getCoalescedEvents();
        
        ts = e.timeStamp;
        
        for (e of events) {
          x = Tegaki.getCursorPos(e, 0);
          y = Tegaki.getCursorPos(e, 1);
          
          if (!tool.enabledDynamics()) {
            Tegaki.recordEvent(TegakiEventDrawNoP, ts, x, y);
          }
          else {
            p = TegakiPressure.toShort(e.pressure);
            TegakiPressure.push(p);
            Tegaki.recordEvent(TegakiEventDraw, ts, x, y, p);
          }
          
          tool.draw(x, y);
        }
      }
      else {
        x = Tegaki.getCursorPos(e, 0);
        y = Tegaki.getCursorPos(e, 1);
        p = TegakiPressure.toShort(e.pressure);
        Tegaki.recordEvent(TegakiEventDraw, e.timeStamp, x, y, p);
        TegakiPressure.push(p);
        tool.draw(x, y);
      }
    }
    else {
      x = Tegaki.getCursorPos(e, 0);
      y = Tegaki.getCursorPos(e, 1);
    }
    
    if (Tegaki.cursor) {
      TegakiCursor.render(x, y);
    }
  },
  
  onPointerDown: function(e) {
    var x, y, tool, p;
    
    if (Tegaki.isScrollbarClick(e)) {
      return;
    }
    
    Tegaki.activePointerId = e.pointerId;
    
    Tegaki.activePointerIsPen = e.pointerType === 'pen';
    
    if (Tegaki.activeLayer === null) {
      if (e.target.parentNode === Tegaki.layersCnt) {
        TegakiUI.printMsg(TegakiStrings.noActiveLayer);
      }
      
      return;
    }
    
    if (!TegakiLayers.getActiveLayer().visible) {
      if (e.target.parentNode === Tegaki.layersCnt) {
        TegakiUI.printMsg(TegakiStrings.hiddenActiveLayer);
      }
      
      return;
    }
    
    x = Tegaki.getCursorPos(e, 0);
    y = Tegaki.getCursorPos(e, 1);
    
    if (e.button === 2 || e.altKey) {
      e.preventDefault();
      
      Tegaki.isPainting = false;
      
      Tegaki.tools.pipette.draw(x, y);
    }
    else if (e.button === 0) {
      e.preventDefault();
      
      tool = Tegaki.tool;

      if (!tool.enabledDynamics()) {
        Tegaki.recordEvent(TegakiEventDrawStartNoP, e.timeStamp, x, y);
      }
      else {
        p = TegakiPressure.toShort(e.pressure);
        TegakiPressure.push(p);
        Tegaki.recordEvent(TegakiEventDrawStart, e.timeStamp, x, y, p);
      }
      
      Tegaki.isPainting = true;
      
      TegakiHistory.pendingAction = new TegakiHistoryActions.Draw(
        Tegaki.activeLayer.id
      );
      
      TegakiHistory.pendingAction.addCanvasState(Tegaki.activeLayer.imageData, 0);
      
      tool.start(x, y);
    }
    
    if (Tegaki.cursor) {
      TegakiCursor.render(x, y);
    }
  },
  
  onPointerUp: function(e) {
    Tegaki.activePointerId = e.pointerId;
    
    Tegaki.activePointerIsPen = false;
    
    if (Tegaki.isPainting) {
      Tegaki.recordEvent(TegakiEventDrawCommit, e.timeStamp);
      Tegaki.tool.commit();
      TegakiUI.updateLayerPreview(Tegaki.activeLayer);
      TegakiHistory.pendingAction.addCanvasState(Tegaki.activeLayer.imageData, 1);
      TegakiHistory.push(TegakiHistory.pendingAction);
      Tegaki.isPainting = false;
    }
  },
  
  onDummy: function(e) {
    e.preventDefault();
    e.stopPropagation();
  },
  
  recordEvent(klass, ...args) {
    if (Tegaki.replayRecorder) {
      Tegaki.replayRecorder.push(new klass(...args));
    }
  }
};
var TegakiColorPalettes = [
  [
    '#ffffff', '#000000', '#888888', '#b47575', '#c096c0',
    '#fa9696', '#8080ff', '#ffb6ff', '#e7e58d', '#25c7c9',
    '#99cb7b', '#e7962d', '#f9ddcf', '#fcece2'
  ],
  
  [
    '#000000', '#ffffff', '#7f7f7f', '#c3c3c3', '#880015', '#b97a57', '#ed1c24',
    '#ffaec9', '#ff7f27', '#ffc90e', '#fff200', '#efe4b0', '#22b14c', '#b5e61d',
    '#00a2e8', '#99d9ea', '#3f48cc', '#7092be', '#a349a4', '#c8bfe7'
  ],
  
  [
    '#000000', '#ffffff', '#8a8a8a', '#cacaca', '#fcece2', '#f9ddcf', '#e0a899', '#a05b53',
    '#7a444a', '#960018', '#c41e3a', '#de4537', '#ff3300', '#ff9800', '#ffc107',
    '#ffd700', '#ffeb3b', '#ffffcc', '#f3e5ab', '#cddc39', '#8bc34a', '#4caf50', '#3e8948',
    '#355e3b', '#3eb489', '#f0f8ff', '#87ceeb', '#6699cc', '#007fff', '#2d68c4', '#364478',
    '#352c4a', '#9c27b0', '#da70d6', '#ff0090', '#fa8072', '#f19cbb', '#c78b95'
  ]
];
var TegakiPressure = {
  pressureNow: 0.0,
  pressureThen: 0.0,
  
  toShort: function(pressure) {
    return 0 | (pressure * 65535);
  },
  
  get: function() {
    return this.pressureNow;
  },
  
  lerp: function(t) {
    return this.pressureThen * (1.0 - t) + this.pressureNow * t;
  },
  
  push: function(p) {
    this.pressureThen = this.pressureNow;
    this.pressureNow = p / 65535;
  },
  
  set: function(p) {
    this.pressureThen = this.pressureNow = p / 65535;
  }
};
class TegakiEvent_void {
  constructor() {
    this.size = 5;
  }
  
  pack(w) {
    w.writeUint8(this.type);
    w.writeUint32(this.timeStamp);
  }
  
  static unpack(r) {
    return new this(r.readUint32());
  }
}

class TegakiEvent_c {
  constructor() {
    this.size = 6;
  }
  
  pack(w) {
    w.writeUint8(this.type);
    w.writeUint32(this.timeStamp);
    w.writeUint8(this.value);
  }
  
  static unpack(r) {
    return new this(r.readUint32(), r.readUint8());
  }
}

// ---

class TegakiEventPrelude extends TegakiEvent_void {
  constructor(timeStamp) {
    super();
    this.timeStamp = timeStamp;
    this.type = TegakiEvents[this.constructor.name][0];
  }
  
  dispatch() {}
}

class TegakiEventConclusion extends TegakiEvent_void {
  constructor(timeStamp) {
    super();
    this.timeStamp = timeStamp;
    this.type = TegakiEvents[this.constructor.name][0];
  }
  
  dispatch() {}
}

class TegakiEventHistoryDummy extends TegakiEvent_void {
  constructor(timeStamp) {
    super();
    this.timeStamp = timeStamp;
    this.type = TegakiEvents[this.constructor.name][0];
  }
  
  dispatch() {
    TegakiHistory.push(new TegakiHistoryActions.Dummy());
  }
}

class TegakiEventDrawStart {
  constructor(timeStamp, x, y, pressure) {
    this.timeStamp = timeStamp;
    this.x = x;
    this.y = y;
    this.pressure = pressure;
    this.type = TegakiEvents[this.constructor.name][0];
    this.size = 11;
  }
  
  pack(w) {
    w.writeUint8(this.type);
    w.writeUint32(this.timeStamp);
    w.writeInt16(this.x);
    w.writeInt16(this.y);
    w.writeUint16(this.pressure);
  }
  
  static unpack(r) {
    var timeStamp, x, y, pressure;
    
    timeStamp = r.readUint32();
    x = r.readInt16();
    y = r.readInt16();
    pressure = r.readUint16();
    
    return new TegakiEventDrawStart(timeStamp, x, y, pressure);
  }
  
  dispatch() {
    TegakiPressure.set(this.pressure);
    
    TegakiHistory.pendingAction = new TegakiHistoryActions.Draw(
      Tegaki.activeLayer.id
    );
    
    TegakiHistory.pendingAction.addCanvasState(Tegaki.activeLayer.imageData, 0);
    
    Tegaki.tool.start(this.x, this.y);
  }
}

class TegakiEventDrawStartNoP {
  constructor(timeStamp, x, y) {
    this.timeStamp = timeStamp;
    this.x = x;
    this.y = y;
    this.type = TegakiEvents[this.constructor.name][0];
    this.size = 9;
  }
  
  pack(w) {
    w.writeUint8(this.type);
    w.writeUint32(this.timeStamp);
    w.writeInt16(this.x);
    w.writeInt16(this.y);
  }
  
  static unpack(r) {
    var timeStamp, x, y;
    
    timeStamp = r.readUint32();
    x = r.readInt16();
    y = r.readInt16();
    
    return new TegakiEventDrawStartNoP(timeStamp, x, y);
  }
  
  dispatch() {
    TegakiPressure.set(0.5);
    
    TegakiHistory.pendingAction = new TegakiHistoryActions.Draw(
      Tegaki.activeLayer.id
    );
    
    TegakiHistory.pendingAction.addCanvasState(Tegaki.activeLayer.imageData, 0);
    
    Tegaki.tool.start(this.x, this.y);
  }
}

class TegakiEventDraw {
  constructor(timeStamp, x, y, pressure) {
    this.timeStamp = timeStamp;
    this.x = x;
    this.y = y;
    this.pressure = pressure;
    this.type = TegakiEvents[this.constructor.name][0];
    this.size = 11;
  }
  
  pack(w) {
    w.writeUint8(this.type);
    w.writeUint32(this.timeStamp);
    w.writeInt16(this.x);
    w.writeInt16(this.y);
    w.writeUint16(this.pressure);
  }
  
  static unpack(r) {
    var timeStamp, x, y, pressure;
    
    timeStamp = r.readUint32();
    x = r.readInt16();
    y = r.readInt16();
    pressure = r.readUint16();
    
    return new TegakiEventDraw(timeStamp, x, y, pressure);
  }
  
  dispatch() {
    TegakiPressure.push(this.pressure);
    Tegaki.tool.draw(this.x, this.y);
  }
}

class TegakiEventDrawNoP {
  constructor(timeStamp, x, y) {
    this.timeStamp = timeStamp;
    this.x = x;
    this.y = y;
    this.type = TegakiEvents[this.constructor.name][0];
    this.size = 9;
  }
  
  pack(w) {
    w.writeUint8(this.type);
    w.writeUint32(this.timeStamp);
    w.writeInt16(this.x);
    w.writeInt16(this.y);
  }
  
  static unpack(r) {
    var timeStamp, x, y;
    
    timeStamp = r.readUint32();
    x = r.readInt16();
    y = r.readInt16();
    
    return new TegakiEventDraw(timeStamp, x, y);
  }
  
  dispatch() {
    TegakiPressure.push(0.5);
    Tegaki.tool.draw(this.x, this.y);
  }
}

class TegakiEventDrawCommit extends TegakiEvent_void {
  constructor(timeStamp) {
    super();
    this.timeStamp = timeStamp;
    this.type = TegakiEvents[this.constructor.name][0];
  }
  
  dispatch() {
    Tegaki.tool.commit();
    TegakiUI.updateLayerPreview(Tegaki.activeLayer);
    TegakiHistory.pendingAction.addCanvasState(Tegaki.activeLayer.imageData, 1);
    TegakiHistory.push(TegakiHistory.pendingAction);
    Tegaki.isPainting = false;
  }
}

class TegakiEventUndo extends TegakiEvent_void {
  constructor(timeStamp) {
    super();
    this.timeStamp = timeStamp;
    this.type = TegakiEvents[this.constructor.name][0];
  }
  
  dispatch() {
    TegakiHistory.undo();
  }
}

class TegakiEventRedo extends TegakiEvent_void {
  constructor(timeStamp) {
    super();
    this.timeStamp = timeStamp;
    this.type = TegakiEvents[this.constructor.name][0];
  }
  
  dispatch() {
    TegakiHistory.redo();
  }
}

class TegakiEventSetColor {
  constructor(timeStamp, rgb) {
    this.timeStamp = timeStamp;
    [this.r, this.g, this.b] = rgb;
    this.type = TegakiEvents[this.constructor.name][0];
    this.size = 8;
    this.coalesce = true;
  }
  
  pack(w) {
    w.writeUint8(this.type);
    w.writeUint32(this.timeStamp);
    w.writeUint8(this.r);
    w.writeUint8(this.g);
    w.writeUint8(this.b);
  }
  
  static unpack(r) {
    var timeStamp, rgb;
    
    timeStamp = r.readUint32();
    
    rgb = [r.readUint8(), r.readUint8(), r.readUint8()];
    
    return new TegakiEventSetColor(timeStamp, rgb);
  }
  
  dispatch() {
    Tegaki.setToolColorRGB(this.r, this.g, this.b);
  }
}

class TegakiEventSetTool extends TegakiEvent_c {
  constructor(timeStamp, value) {
    super();
    this.timeStamp = timeStamp;
    this.value = value;
    this.type = TegakiEvents[this.constructor.name][0];
    this.coalesce = true;
  }
  
  dispatch() {
    Tegaki.setToolById(this.value);
  }
}

class TegakiEventSetToolSize extends TegakiEvent_c {
  constructor(timeStamp, value) {
    super();
    this.timeStamp = timeStamp;
    this.value = value;
    this.type = TegakiEvents[this.constructor.name][0];
    this.coalesce = true;
  }
  
  dispatch() {
    Tegaki.setToolSize(this.value);
  }
}

class TegakiEventSetToolAlpha {
  constructor(timeStamp, value) {
    this.timeStamp = timeStamp;
    this.value = value;
    this.type = TegakiEvents[this.constructor.name][0];
    this.coalesce = true;
    this.size = 9;
  }
  
  pack(w) {
    w.writeUint8(this.type);
    w.writeUint32(this.timeStamp);
    w.writeFloat32(this.value);
  }
  
  static unpack(r) {
    return new this(r.readUint32(), r.readFloat32());
  }
  
  dispatch() {
    Tegaki.setToolAlpha(this.value);
  }
}

class TegakiEventSetToolFlow {
  constructor(timeStamp, value) {
    this.timeStamp = timeStamp;
    this.value = value;
    this.type = TegakiEvents[this.constructor.name][0];
    this.coalesce = true;
    this.size = 9;
  }
  
  pack(w) {
    w.writeUint8(this.type);
    w.writeUint32(this.timeStamp);
    w.writeFloat32(this.value);
  }
  
  static unpack(r) {
    return new this(r.readUint32(), r.readFloat32());
  }
  
  dispatch() {
    Tegaki.setToolFlow(this.value);
  }
}

class TegakiEventPreserveAlpha extends TegakiEvent_c {
  constructor(timeStamp, value) {
    super();
    this.timeStamp = timeStamp;
    this.value = value;
    this.type = TegakiEvents[this.constructor.name][0];
    this.coalesce = true;
  }
  
  dispatch() {
    Tegaki.setToolPreserveAlpha(!!this.value);
  }
}

class TegakiEventSetToolSizeDynamics extends TegakiEvent_c {
  constructor(timeStamp, value) {
    super();
    this.timeStamp = timeStamp;
    this.value = value;
    this.type = TegakiEvents[this.constructor.name][0];
    this.coalesce = true;
  }
  
  dispatch() {
    Tegaki.setToolSizeDynamics(!!this.value);
  }
}

class TegakiEventSetToolAlphaDynamics extends TegakiEvent_c {
  constructor(timeStamp, value) {
    super();
    this.timeStamp = timeStamp;
    this.value = value;
    this.type = TegakiEvents[this.constructor.name][0];
    this.coalesce = true;
  }
  
  dispatch() {
    Tegaki.setToolAlphaDynamics(!!this.value);
  }
}

class TegakiEventSetToolFlowDynamics extends TegakiEvent_c {
  constructor(timeStamp, value) {
    super();
    this.timeStamp = timeStamp;
    this.value = value;
    this.type = TegakiEvents[this.constructor.name][0];
    this.coalesce = true;
  }
  
  dispatch() {
    Tegaki.setToolFlowDynamics(!!this.value);
  }
}

class TegakiEventSetToolTip extends TegakiEvent_c {
  constructor(timeStamp, value) {
    super();
    this.timeStamp = timeStamp;
    this.value = value;
    this.type = TegakiEvents[this.constructor.name][0];
    this.coalesce = true;
  }
  
  dispatch() {
    Tegaki.setToolTip(this.value);
  }
}

class TegakiEventAddLayer extends TegakiEvent_void {
  constructor(timeStamp) {
    super();
    this.timeStamp = timeStamp;
    this.type = TegakiEvents[this.constructor.name][0];
  }
  
  dispatch() {
    Tegaki.addLayer();
  }
}

class TegakiEventDeleteLayers extends TegakiEvent_void {
  constructor(timeStamp) {
    super();
    this.timeStamp = timeStamp;
    this.type = TegakiEvents[this.constructor.name][0];
  }
  
  dispatch() {
    Tegaki.deleteSelectedLayers();
  }
}

class TegakiEventMoveLayers extends TegakiEvent_c {
  constructor(timeStamp, value) {
    super();
    this.timeStamp = timeStamp;
    this.value = value;
    this.type = TegakiEvents[this.constructor.name][0];
  }
  
  dispatch() {
    Tegaki.moveSelectedLayers(this.value);
  }
}

class TegakiEventMergeLayers extends TegakiEvent_void {
  constructor(timeStamp) {
    super();
    this.timeStamp = timeStamp;
    this.type = TegakiEvents[this.constructor.name][0];
  }
  
  dispatch() {
    Tegaki.mergeSelectedLayers();
  }
}

class TegakiEventToggleLayerVisibility extends TegakiEvent_c {
  constructor(timeStamp, value) {
    super();
    this.timeStamp = timeStamp;
    this.value = value;
    this.type = TegakiEvents[this.constructor.name][0];
  }
  
  dispatch() {
    Tegaki.toggleLayerVisibility(this.value);
  }
}

class TegakiEventSetActiveLayer extends TegakiEvent_c {
  constructor(timeStamp, value) {
    super();
    this.timeStamp = timeStamp;
    this.value = value;
    this.type = TegakiEvents[this.constructor.name][0];
  }
  
  dispatch() {
    Tegaki.setActiveLayer(this.value);
  }
}

class TegakiEventToggleLayerSelection extends TegakiEvent_c {
  constructor(timeStamp, value) {
    super();
    this.timeStamp = timeStamp;
    this.value = value;
    this.type = TegakiEvents[this.constructor.name][0];
  }
  
  dispatch() {
    Tegaki.toggleSelectedLayer(this.value);
  }
}

class TegakiEventSetSelectedLayersAlpha {
  constructor(timeStamp, value) {
    this.timeStamp = timeStamp;
    this.value = value;
    this.type = TegakiEvents[this.constructor.name][0];
    this.coalesce = true;
    this.size = 9;
  }
  
  pack(w) {
    w.writeUint8(this.type);
    w.writeUint32(this.timeStamp);
    w.writeFloat32(this.value);
  }
  
  static unpack(r) {
    return new this(r.readUint32(), r.readFloat32());
  }
  
  dispatch() {
    Tegaki.setSelectedLayersAlpha(this.value);
  }
}

const TegakiEvents = Object.freeze({
  TegakiEventPrelude:                 [0,   TegakiEventPrelude],
  
  TegakiEventDrawStart:               [1,   TegakiEventDrawStart],
  TegakiEventDraw:                    [2,   TegakiEventDraw],
  TegakiEventDrawCommit:              [3,   TegakiEventDrawCommit],
  TegakiEventUndo:                    [4,   TegakiEventUndo],
  TegakiEventRedo:                    [5,   TegakiEventRedo],
  TegakiEventSetColor:                [6,   TegakiEventSetColor],
  TegakiEventDrawStartNoP:            [7,   TegakiEventDrawStartNoP],
  TegakiEventDrawNoP:                 [8,   TegakiEventDrawNoP],

  TegakiEventSetTool:                 [10,  TegakiEventSetTool],
  TegakiEventSetToolSize:             [11,  TegakiEventSetToolSize],
  TegakiEventSetToolAlpha:            [12,  TegakiEventSetToolAlpha],
  TegakiEventSetToolSizeDynamics:     [13,  TegakiEventSetToolSizeDynamics],
  TegakiEventSetToolAlphaDynamics:    [14,  TegakiEventSetToolAlphaDynamics],
  TegakiEventSetToolTip:              [15,  TegakiEventSetToolTip],
  TegakiEventPreserveAlpha:           [16,  TegakiEventPreserveAlpha],
  TegakiEventSetToolFlowDynamics:     [17,  TegakiEventSetToolFlowDynamics],
  TegakiEventSetToolFlow:             [18,  TegakiEventSetToolFlow],

  TegakiEventAddLayer:                [20,  TegakiEventAddLayer],
  TegakiEventDeleteLayers:            [21,  TegakiEventDeleteLayers],
  TegakiEventMoveLayers:              [22,  TegakiEventMoveLayers],
  TegakiEventMergeLayers:             [23,  TegakiEventMergeLayers],
  TegakiEventToggleLayerVisibility:   [24,  TegakiEventToggleLayerVisibility],
  TegakiEventSetActiveLayer:          [25,  TegakiEventSetActiveLayer],
  TegakiEventToggleLayerSelection:    [26,  TegakiEventToggleLayerSelection],
  TegakiEventSetSelectedLayersAlpha:  [27,  TegakiEventSetSelectedLayersAlpha],
  
  TegakiEventHistoryDummy:            [254,  TegakiEventHistoryDummy],
  
  TegakiEventConclusion:              [255, TegakiEventConclusion]
});
class TegakiReplayRecorder {
  constructor() {
    this.formatVersion = 1;
    
    this.compressed = true;
    
    this.tegakiVersion = Tegaki.VERSION.split('.').map((v) => +v);
    
    this.canvasWidth = Tegaki.baseWidth;
    this.canvasHeight = Tegaki.baseHeight;
    
    this.bgColor = $T.hexToRgb(Tegaki.bgColor);
    this.toolColor = $T.hexToRgb(Tegaki.toolColor);
    
    this.toolId = Tegaki.tools[Tegaki.defaultTool].id;
    
    this.toolList = this.buildToolList(Tegaki.tools);
    
    this.startTimeStamp = 0;
    this.endTimeStamp = 0;
    
    this.recording = false;
    
    this.events = [];
    
    this.mimeType = 'application/octet-stream';
  }
  
  buildToolList(tools) {
    var k, tool, toolMap;
    
    toolMap = [];
    
    for (k in tools) {
      tool = tools[k];
      
      toolMap.push({
        id: tool.id, 
        size: tool.size,
        alpha: tool.alpha,
        flow: tool.flow,
        step: tool.step,
        sizeDynamicsEnabled: +tool.sizeDynamicsEnabled,
        alphaDynamicsEnabled: +tool.alphaDynamicsEnabled,
        flowDynamicsEnabled: +tool.flowDynamicsEnabled,
        usePreserveAlpha: +tool.usePreserveAlpha,
        tipId: tool.tipId
      });
    }
    
    return toolMap;
  }
  
  start() {
    if (this.recording) {
      return;
    }
    
    if (this.endTimeStamp > 0) {
      this.events.pop();
      this.endTimeStamp = 0;
    }
    
    if (this.startTimeStamp === 0) {
      this.events.push(new TegakiEventPrelude(performance.now()));
      this.startTimeStamp = Date.now();
    }
    
    this.recording = true;
  }
  
  stop() {
    if (this.startTimeStamp === 0 || !this.recording) {
      return;
    }
    
    this.events.push(new TegakiEventConclusion(performance.now()));
    this.endTimeStamp = Date.now();
    this.recording = false;
  }
  
  push(e) {
    if (this.recording) {
      if (e.coalesce && this.events[this.events.length - 1].type === e.type) {
        this.events[this.events.length - 1] = e;
      }
      else {
        this.events.push(e);
      }
    }
  }
  
  getEventStackSize() {
    var e, size;
    
    size = 4;
    
    for (e of this.events) {
      size += e.size;
    }
    
    return size;
  }
  
  getHeaderSize() {
    return 12;
  }
  
  getMetaSize() {
    return 21;
  }
  
  getToolSize() {
    return 19;
  }
  
  getToolListSize() {
    return 2 + (this.toolList.length * this.getToolSize());
  }
  
  writeToolList(w) {
    var tool, field, fields;
    
    fields = [
      ['id', 'Uint8'],
      ['size', 'Uint8'],
      ['alpha', 'Float32'],
      ['step', 'Float32'],
      ['sizeDynamicsEnabled', 'Uint8'],
      ['alphaDynamicsEnabled', 'Uint8'],
      ['usePreserveAlpha', 'Uint8'],
      ['tipId', 'Int8'],
      ['flow', 'Float32'],
      ['flowDynamicsEnabled', 'Uint8'],
    ];
    
    w.writeUint8(this.toolList.length);
    
    w.writeUint8(this.getToolSize());
    
    for (tool of this.toolList) {
      for (field of fields) {
        w['write' + field[1]](tool[field[0]]);
      }
    }
  }
  
  writeMeta(w) {
    w.writeUint16(this.getMetaSize());
    
    w.writeUint32(Math.ceil(this.startTimeStamp / 1000));
    w.writeUint32(Math.ceil(this.endTimeStamp / 1000));
    
    w.writeUint16(this.canvasWidth);
    w.writeUint16(this.canvasHeight);
    
    w.writeUint8(this.bgColor[0]);
    w.writeUint8(this.bgColor[1]);
    w.writeUint8(this.bgColor[2]);
    
    w.writeUint8(this.toolColor[0]);
    w.writeUint8(this.toolColor[1]);
    w.writeUint8(this.toolColor[2]);
    
    w.writeUint8(this.toolId);
  }
  
  writeEventStack(w) {
    var event;
    
    w.writeUint32(this.events.length);
    
    for (event of this.events) {
      event.pack(w);
    }
  }
  
  writeHeader(w, dataSize) {
    w.writeUint8(0x54);
    w.writeUint8(0x47);
    w.writeUint8(0x4B);
    
    w.writeUint8(+this.compressed);
    
    w.writeUint32(dataSize);
    
    w.writeUint8(this.tegakiVersion[0]);
    w.writeUint8(this.tegakiVersion[1]);
    w.writeUint8(this.tegakiVersion[2]);
    w.writeUint8(this.formatVersion);
  }
  
  compressData(w) {
    return UZIP.deflateRaw(new Uint8Array(w.buf), { level: 9 });
  }
  
  toUint8Array() {
    var headerSize, dataSize, data, w, compData, bytes;
    
    if (!this.startTimeStamp || !this.endTimeStamp) {
      return null;
    }
    
    headerSize = this.getHeaderSize();
    dataSize = this.getMetaSize() + this.getToolListSize() + this.getEventStackSize();
    
    data = new ArrayBuffer(dataSize);
    
    w = new TegakiBinWriter(data);
    
    this.writeMeta(w);
    
    this.writeToolList(w);
    
    this.writeEventStack(w);
    
    compData = this.compressData(w);
    //compData = new Uint8Array(data.slice(0));
    
    w = new TegakiBinWriter(new ArrayBuffer(headerSize + compData.length));
    
    this.writeHeader(w, dataSize);
    
    bytes = new Uint8Array(w.buf);
    
    bytes.set(compData, headerSize);
    
    return bytes;
  }
  
  toBlob() {
    var ary = this.toUint8Array();
    
    if (!ary) {
      return null;
    }
    
    return new Blob([ary.buffer], { type: this.mimeType });
  }
}
class TegakiReplayViewer {
  constructor() {
    this.formatVersion = 1;
    
    this.compressed = true;
    
    this.tegakiVersion = [0, 0, 0];
    
    this.dataSize = 0;
    
    this.canvasWidth = 0;
    this.canvasHeight = 0;
    
    this.bgColor = [0, 0, 0];
    this.toolColor = [0, 0, 0];
    
    this.toolId = 1;
    
    this.toolMap = {};
    
    this.startTimeStamp = 0;
    this.endTimeStamp = 0;
    
    this.loaded = false;
    this.playing = false;
    this.gapless = true;
    
    this.autoPaused = false;
    
    this.destroyed = false;
    
    this.speedIndex = 1;
    this.speedList = [0.5, 1.0, 2.0, 5.0, 10.0];
    this.speed = this.speedList[this.speedIndex];
    
    this.maxEventsPerFrame = 25;
    
    this.maxEventCount = 8640000;
    
    this.events = [];
    
    this.preludePos = 0.0;
    this.currentPos = 0.0;
    this.conclusionPos = 0.0;
    this.duration = 0.0;
    
    this.playTimeStart = 0.0;
    this.playTimeCurrent = 0.0;
    
    this.eventIndex = 0;
    
    this.maxCanvasWH = 8192;
    
    this.maxGapTime = 3000;
    
    this.uiAccTime = 0;
    
    this.onFrameThis = this.onFrame.bind(this);
  }
  
  destroy() {
    this.destroyed = true;
    this.pause();
    this.events = null;
  }
  
  speedUp() {
    if (this.speedIndex + 1 < this.speedList.length) {
      this.speed = this.speedList[++this.speedIndex];
    }
  }
  
  slowDown() {
    if (this.speedIndex - 1 >= 0) {
      this.speed = this.speedList[--this.speedIndex];
    }
  }
  
  toggleGapless() {
    this.gapless = !this.gapless;
  }
  
  getCurrentPos() {
    return this.currentPos;
  }
  
  getDuration() {
    return this.duration;
  }
  
  loadFromURL(url) {
    fetch(url)
      .then((resp) => this.onResponseReady(resp))
      .catch((err) => this.onLoadError(err));
  }
  
  onResponseReady(resp) {
    if (resp.ok) {
      resp.arrayBuffer()
        .then((buf) => this.onResponseBodyReady(buf))
        .catch((err) => this.onLoadError(err));
    }
    else {
      this.onLoadError(resp.statusText);
    }
  }
  
  onResponseBodyReady(data) {
    this.loadFromBuffer(data);
    Tegaki.onReplayLoaded();
  }
  
  onLoadError(err) {
    TegakiUI.printMsg(TegakiStrings.errorLoadReplay + err, 0);
  }
  
  autoPause() {
    this.autoPaused = true;
    this.pause();
  }
  
  pause(rewind) {
    window.cancelAnimationFrame(this.onFrameThis);
    
    this.playing = false;
    
    if (rewind) {
      this.currentPos = 0;
      this.eventIndex = 0;
    }
    
    Tegaki.onReplayTimeChanged();
    Tegaki.onReplayPlayPauseChanged();
  }
  
  rewind() {
    this.autoPaused = false;
    this.pause(true);
    Tegaki.onReplayReset();
  }
  
  play() {
    this.playTimeStart = performance.now();
    this.playTimeCurrent = this.playTimeStart;
    
    this.playing = true;
    this.autoPaused = false;
    
    this.uiAccTime = 0;
    
    Tegaki.onReplayPlayPauseChanged();
    
    window.requestAnimationFrame(this.onFrameThis);
  }
  
  togglePlayPause() {
    if (this.playing) {
      this.pause();
    }
    else {
      this.play();
    }
  }
  
  onFrame(ts) {
    var delta = ts - this.playTimeCurrent;
    
    if (!this.playing) {
      return;
    }
    
    this.playTimeCurrent = ts;
    
    this.step(delta);
    
    this.uiAccTime += delta;
    
    if (this.uiAccTime > 1000) {
      Tegaki.onReplayTimeChanged();
      this.uiAccTime = 0;
    }
    
    if (this.currentPos < this.duration) {
      window.requestAnimationFrame(this.onFrameThis);
    }
    else {
      this.pause();
    }
  }
  
  step(delta) {
    var event, currentEventTime, i;
    
    this.currentPos += (delta * this.speed);
    
    currentEventTime = this.currentPos + this.preludePos;
    
    if (this.gapless && this.eventIndex < this.events.length) {
      event = this.events[this.eventIndex];
      
      if (event.timeStamp - currentEventTime > this.maxGapTime) {
        this.currentPos = event.timeStamp - this.preludePos;
        currentEventTime = event.timeStamp;
      }
    }
    
    i = 0;
    
    while (this.eventIndex < this.events.length) {
      event = this.events[this.eventIndex];
      
      if (event.timeStamp <= currentEventTime) {
        if (i >= this.maxEventsPerFrame) {
          this.currentPos = event.timeStamp - this.preludePos;
          break;
        }
        
        event.dispatch();
        
        ++this.eventIndex;
        ++i;
      }
      else {
        break;
      }
    }
  }
  
  getEventIdMap() {
    var map, key, val;
    
    map = {};
    
    for (key in TegakiEvents) {
      val = TegakiEvents[key];
      map[val[0]] = val[1];
    }
    
    return map;
  }
  
  readToolMap(r) {
    var i, len, size, tool, field, fields, pos;
    
    this.toolMap = {};
    
    fields = [
      ['id', 'Uint8'],
      ['size', 'Uint8'],
      ['alpha', 'Float32'],
      ['step', 'Float32'],
      ['sizeDynamicsEnabled', 'Uint8'],
      ['alphaDynamicsEnabled', 'Uint8'],
      ['usePreserveAlpha', 'Uint8'],
      ['tipId', 'Int8'],
      ['flow', 'Float32'],
      ['flowDynamicsEnabled', 'Uint8'],
    ];
    
    len = r.readUint8();
    
    size = r.readUint8();
    
    for (i = 0; i < len; ++i) {
      pos = r.pos + size;
      
      tool = {};
      
      for (field of fields) {
        if (r.pos >= pos) {
          break;
        }
        
        tool[field[0]] = r['read' + field[1]]();
      }
      
      this.toolMap[tool.id] = tool;
      
      r.pos = pos;
    }
  }
  
  readHeader(r) {
    var tgk;
    
    tgk = String.fromCharCode(r.readUint8(), r.readUint8(), r.readUint8());
    
    if (tgk !== 'TGK') {
      throw 'invalid header';
    }
    
    this.compressed = r.readUint8() === 1;
    
    this.dataSize = r.readUint32();
    
    this.tegakiVersion[0] = r.readUint8();
    this.tegakiVersion[1] = r.readUint8();
    this.tegakiVersion[2] = r.readUint8();
    
    this.formatVersion = r.readUint8();
  }
  
  decompressData(r) {
    return UZIP.inflateRaw(
      new Uint8Array(r.buf, r.pos),
      new Uint8Array(this.dataSize)
    );
  }
  
  readMeta(r) {
    var pos, size;
    
    size = r.readUint16();
    
    pos = r.pos + size - 2;
    
    this.startTimeStamp = r.readUint32() * 1000;
    this.endTimeStamp = r.readUint32() * 1000;
    
    this.canvasWidth = r.readUint16();
    this.canvasHeight = r.readUint16();
    
    if (this.canvasWidth > this.maxCanvasWH
      || this.canvasHeight > this.maxCanvasWH) {
      throw 'canvas too large';
    }
    
    this.bgColor[0] = r.readUint8();
    this.bgColor[1] = r.readUint8();
    this.bgColor[2] = r.readUint8();
    
    this.toolColor[0] = r.readUint8();
    this.toolColor[1] = r.readUint8();
    this.toolColor[2] = r.readUint8();
    
    this.toolId = r.readUint8();
    
    r.pos = pos;
  }
  
  readEventStack(r) {
    var i, len, type, klass, event, eventMap;
    
    eventMap = this.getEventIdMap();
    
    len = r.readUint32();
    
    if (len < 1 || len > this.maxEventCount) {
      throw 'invalid event count';
    }
    
    for (i = 0; i < len; ++i) {
      type = r.readUint8();
      
      klass = eventMap[type];
      
      if (!klass) {
        throw 'invalid event id';
      }
      
      event = klass.unpack(r);
      
      this.events.push(event);
    }
    
    if (this.events[0].type !== TegakiEvents.TegakiEventPrelude[0]) {
      throw 'invalid prelude';
    }
    
    if (this.events[len - 1].type !== TegakiEvents.TegakiEventConclusion[0]) {
      throw 'invalid conclusion';
    }
    
    this.preludePos = this.events[0].timeStamp;
    this.conclusionPos = this.events[len - 1].timeStamp;
    
    this.duration = this.conclusionPos - this.preludePos;
    
    if (this.duration <= 0) {
      throw 'invalid duration';
    }
  }
  
  loadFromBuffer(buffer) {
    var r, data;
    
    if (this.destroyed || this.loaded) {
      return false;
    }
    
    r = new TegakiBinReader(buffer);
    
    this.readHeader(r);
    
    data = this.decompressData(r);
    
    r = new TegakiBinReader(data.buffer);
    
    this.readMeta(r);
    
    this.readToolMap(r);
    
    this.readEventStack(r);
    
    this.loaded = true;
    
    return true;
  }
}
var TegakiUI = {
  draggedNode: null,
  
  draggedLabelLastX: 0,
  draggedLabelFn: null,
  
  statusTimeout: 0,
  
  layerPreviewCtxCache: new WeakMap(),
  
  getLayerPreviewSize: function() {
    return $T.calcThumbSize(Tegaki.baseWidth, Tegaki.baseHeight, 24);
  },
  
  setupDragLabel: function(e, moveFn) {
    TegakiUI.draggedLabelFn = moveFn;
    TegakiUI.draggedLabelLastX = e.clientX;
    $T.on(document, 'pointermove', TegakiUI.processDragLabel);
    $T.on(document, 'pointerup', TegakiUI.clearDragLabel);
  },
  
  processDragLabel: function(e) {
    TegakiUI.draggedLabelFn.call(Tegaki, e.clientX - TegakiUI.draggedLabelLastX);
    TegakiUI.draggedLabelLastX = e.clientX;
  },
  
  clearDragLabel: function(e) {
    $T.off(document, 'pointermove', TegakiUI.processDragLabel);
    $T.off(document, 'pointerup', TegakiUI.clearDragLabel);
  },
  
  printMsg: function(str, timeout = 5000) {
    TegakiUI.clearMsg();
    
    $T.id('tegaki-status-output').textContent = str;
    
    if (timeout > 0) {
      TegakiUI.statusTimeout = setTimeout(TegakiUI.clearMsg, 5000);
    }
  },
  
  clearMsg: function() {
    if (TegakiUI.statusTimeout) {
      clearTimeout(TegakiUI.statusTimeout);
      TegakiUI.statusTimeout = 0;
    }
    
    $T.id('tegaki-status-output').textContent = '';
  },
  
  buildUI: function() {
    var bg, cnt, el, ctrl, layersCnt, canvasCnt;
    
    //
    // Grid container
    //
    bg = $T.el('div');
    bg.id = 'tegaki';
    
    //
    // Menu area
    //
    el = $T.el('div');
    el.id = 'tegaki-menu-cnt';
    
    if (!Tegaki.replayMode) {
      el.appendChild(TegakiUI.buildMenuBar());
    }
    else {
      el.appendChild(TegakiUI.buildViewerMenuBar());
      el.appendChild(TegakiUI.buildReplayControls());
    }
    
    el.appendChild(TegakiUI.buildToolModeBar());
    
    bg.appendChild(el);
    
    bg.appendChild(TegakiUI.buildDummyFilePicker());
    
    //
    // Tools area
    //
    cnt = $T.el('div');
    cnt.id = 'tegaki-tools-cnt';
    
    cnt.appendChild(TegakiUI.buildToolsMenu());
    
    bg.appendChild(cnt);
    
    //
    // Canvas area
    //
    [canvasCnt, layersCnt] = TegakiUI.buildCanvasCnt();
    
    bg.appendChild(canvasCnt);
    
    //
    // Controls area
    //
    ctrl = $T.el('div');
    ctrl.id = 'tegaki-ctrl-cnt';
    
    // Zoom control
    ctrl.appendChild(TegakiUI.buildZoomCtrlGroup());
    
    // Colorpicker
    ctrl.appendChild(
      TegakiUI.buildColorCtrlGroup(Tegaki.toolColor)
    );
    
    // Size control
    ctrl.appendChild(TegakiUI.buildSizeCtrlGroup());
    
    // Alpha control
    ctrl.appendChild(TegakiUI.buildAlphaCtrlGroup());
    
    // Flow control
    ctrl.appendChild(TegakiUI.buildFlowCtrlGroup());
    
    // Layers control
    ctrl.appendChild(TegakiUI.buildLayersCtrlGroup());
    
    // ---
    
    bg.appendChild(ctrl);
    
    //
    // Status area
    //
    bg.appendChild(TegakiUI.buildStatusCnt());
    
    return [bg, canvasCnt, layersCnt];
  },
  
  buildDummyFilePicker: function() {
    var el = $T.el('input');
    
    el.type = 'file';
    el.id = 'tegaki-filepicker';
    el.className = 'tegaki-hidden';
    el.accept = 'image/png, image/jpeg';
    $T.on(el, 'change', Tegaki.onOpenFileSelected);
    
    return el;
  },
  
  buildMenuBar: function() {
    var frag, btn;
    
    frag = $T.el('div');
    frag.id = 'tegaki-menu-bar';
    
    btn = $T.el('span');
    btn.className = 'tegaki-mb-btn';
    btn.textContent = TegakiStrings.newCanvas;
    $T.on(btn, 'click', Tegaki.onNewClick);
    frag.appendChild(btn);
    
    btn = $T.el('span');
    btn.className = 'tegaki-mb-btn';
    btn.textContent = TegakiStrings.open;
    $T.on(btn, 'click', Tegaki.onOpenClick);
    frag.appendChild(btn);
    
    btn = $T.el('span');
    btn.className = 'tegaki-mb-btn';
    btn.textContent = TegakiStrings.export;
    $T.on(btn, 'click', Tegaki.onExportClick);
    frag.appendChild(btn);
    
    btn = $T.el('span');
    btn.id = 'tegaki-undo-btn';
    btn.className = 'tegaki-mb-btn';
    btn.textContent = TegakiStrings.undo;
    btn.title = TegakiKeybinds.getCaption('undo');
    $T.on(btn, 'click', Tegaki.onUndoClick);
    frag.appendChild(btn);
    
    btn = $T.el('span');
    btn.id = 'tegaki-redo-btn';
    btn.className = 'tegaki-mb-btn';
    btn.textContent = TegakiStrings.redo;
    btn.title = TegakiKeybinds.getCaption('redo');
    $T.on(btn, 'click', Tegaki.onRedoClick);
    frag.appendChild(btn);
    
    btn = $T.el('span');
    btn.className = 'tegaki-mb-btn';
    btn.textContent = TegakiStrings.close;
    $T.on(btn, 'click', Tegaki.onCancelClick);
    frag.appendChild(btn);
    
    btn = $T.el('span');
    btn.id = 'tegaki-finish-btn';
    btn.className = 'tegaki-mb-btn';
    btn.textContent = TegakiStrings.finish;
    $T.on(btn, 'click', Tegaki.onDoneClick);
    frag.appendChild(btn);
    
    return frag;
  },
  
  buildViewerMenuBar: function() {
    var frag, btn;
    
    frag = $T.el('div');
    frag.id = 'tegaki-menu-bar';
    
    btn = $T.el('span');
    btn.id = 'tegaki-finish-btn';
    btn.className = 'tegaki-mb-btn';
    btn.textContent = TegakiStrings.close;
    $T.on(btn, 'click', Tegaki.onCloseViewerClick);
    frag.appendChild(btn);
    
    return frag;
  },
  
  buildToolModeBar: function() {
    var cnt, grp, el, btn;
    
    cnt = $T.el('div');
    cnt.id = 'tegaki-toolmode-bar';
    
    if (!Tegaki.tool) {
      cnt.classList.add('tegaki-hidden');
    }
    
    // Dynamics
    grp = $T.el('span');
    grp.id = 'tegaki-tool-mode-dynamics';
    grp.className = 'tegaki-toolmode-grp';
    
    el = $T.el('span');
    el.className = 'tegaki-toolmode-lbl';
    el.textContent = TegakiStrings.pressure;
    grp.appendChild(el);
    
    el = $T.el('span');
    el.id = 'tegaki-tool-mode-dynamics-ctrl';
    el.className = 'tegaki-toolmode-ctrl';
    
    btn = $T.el('span');
    btn.id = 'tegaki-tool-mode-dynamics-size';
    btn.className = 'tegaki-sw-btn';
    btn.textContent = TegakiStrings.size;
    $T.on(btn, 'mousedown', Tegaki.onToolPressureSizeClick);
    el.appendChild(btn);
    
    btn = $T.el('span');
    btn.id = 'tegaki-tool-mode-dynamics-alpha';
    btn.className = 'tegaki-sw-btn';
    btn.textContent = TegakiStrings.alpha;
    $T.on(btn, 'mousedown', Tegaki.onToolPressureAlphaClick);
    el.appendChild(btn);
    
    btn = $T.el('span');
    btn.id = 'tegaki-tool-mode-dynamics-flow';
    btn.className = 'tegaki-sw-btn';
    btn.textContent = TegakiStrings.flow;
    $T.on(btn, 'mousedown', Tegaki.onToolPressureFlowClick);
    el.appendChild(btn);
    
    grp.appendChild(el);
    
    cnt.appendChild(grp);
    
    // Preserve Alpha
    grp = $T.el('span');
    grp.id = 'tegaki-tool-mode-mask';
    grp.className = 'tegaki-toolmode-grp';
    
    el = $T.el('span');
    el.id = 'tegaki-toolmode-ctrl-tip';
    el.className = 'tegaki-toolmode-ctrl';
    
    btn = $T.el('span');
    btn.id = 'tegaki-tool-mode-mask-alpha';
    btn.className = 'tegaki-sw-btn';
    btn.textContent = TegakiStrings.preserveAlpha;
    $T.on(btn, 'mousedown', Tegaki.onToolPreserveAlphaClick);
    el.appendChild(btn);
    
    grp.appendChild(el);
    
    cnt.appendChild(grp);
    
    // Tip
    grp = $T.el('span');
    grp.id = 'tegaki-tool-mode-tip';
    grp.className = 'tegaki-toolmode-grp';
    
    el = $T.el('span');
    el.className = 'tegaki-toolmode-lbl';
    el.textContent = TegakiStrings.tip;
    grp.appendChild(el);
    
    el = $T.el('span');
    el.id = 'tegaki-tool-mode-tip-ctrl';
    el.className = 'tegaki-toolmode-ctrl';
    grp.appendChild(el);
    
    cnt.appendChild(grp);
    
    return cnt;
  },
  
  buildToolsMenu: function() {
    var grp, el, lbl, name;
    
    grp = $T.el('div');
    grp.id = 'tegaki-tools-grid';
    
    for (name in Tegaki.tools) {
      el = $T.el('span');
      el.setAttribute('data-tool', name);
      
      lbl = TegakiStrings[name];
      
      if (Tegaki.tools[name].keybind) {
        lbl += ' (' + Tegaki.tools[name].keybind.toUpperCase() + ')';
      }
      
      el.setAttribute('title', lbl);
      el.id = 'tegaki-tool-btn-' + name;
      el.className = 'tegaki-tool-btn tegaki-icon tegaki-' + name;
      
      $T.on(el, 'click', Tegaki.onToolClick);
      
      grp.appendChild(el);
    }
    
    return grp;
  },
  
  buildCanvasCnt: function() {
    var canvasCnt, wrap, layersCnt;
    
    canvasCnt = $T.el('div');
    canvasCnt.id = 'tegaki-canvas-cnt';
    
    wrap =  $T.el('div');
    wrap.id = 'tegaki-layers-wrap';
    
    layersCnt = $T.el('div');
    layersCnt.id = 'tegaki-layers';
    
    wrap.appendChild(layersCnt);
    
    canvasCnt.appendChild(wrap);
    
    return [canvasCnt, layersCnt];
  },
  
  buildCtrlGroup: function(id, title) {
    var cnt, el;
    
    cnt = $T.el('div');
    cnt.className = 'tegaki-ctrlgrp';
    
    if (id) {
      cnt.id = 'tegaki-ctrlgrp-' + id;
    }
    
    if (title !== undefined) {
      el = $T.el('div');
      el.className = 'tegaki-ctrlgrp-title';
      el.textContent = title;
      cnt.appendChild(el);
    }
    
    return cnt;
  },
  
  buildLayersCtrlGroup: function() {
    var el, ctrl, row, cnt;
    
    ctrl = this.buildCtrlGroup('layers', TegakiStrings.layers);
    
    // Layer options row
    row = $T.el('div');
    row.id = 'tegaki-layers-opts';
    
    // Alpha
    cnt = $T.el('div');
    cnt.id = 'tegaki-layer-alpha-cell';
    
    el = $T.el('span');
    el.className = 'tegaki-label-xs tegaki-lbl-c tegaki-drag-lbl';
    el.textContent = TegakiStrings.alpha;
    $T.on(el, 'pointerdown', Tegaki.onLayerAlphaDragStart);
    cnt.appendChild(el);
    
    el = $T.el('input');
    el.id = 'tegaki-layer-alpha-opt';
    el.className = 'tegaki-stealth-input tegaki-range-lbl-xs';
    el.setAttribute('maxlength', 3);
    $T.on(el, 'input', Tegaki.onLayerAlphaChange);
    cnt.appendChild(el);
    
    row.appendChild(cnt);
    
    ctrl.appendChild(row);
    
    el = $T.el('div');
    el.id = 'tegaki-layers-grid';
    ctrl.appendChild(el);
    
    row = $T.el('div');
    row.id = 'tegaki-layers-ctrl';
    
    el = $T.el('span');
    el.title = TegakiStrings.addLayer;
    el.className = 'tegaki-ui-btn tegaki-icon tegaki-plus';
    $T.on(el, 'click', Tegaki.onLayerAddClick);
    row.appendChild(el);
    
    el = $T.el('span');
    el.title = TegakiStrings.delLayers;
    el.className = 'tegaki-ui-btn tegaki-icon tegaki-minus';
    $T.on(el, 'click', Tegaki.onLayerDeleteClick);
    row.appendChild(el);
    
    el = $T.el('span');
    el.id = 'tegaki-layer-merge';
    el.title = TegakiStrings.mergeLayers;
    el.className = 'tegaki-ui-btn tegaki-icon tegaki-level-down';
    $T.on(el, 'click', Tegaki.onMergeLayersClick);
    row.appendChild(el);
    
    el = $T.el('span');
    el.id = 'tegaki-layer-up';
    el.title = TegakiStrings.moveLayerUp;
    el.setAttribute('data-up', '1');
    el.className = 'tegaki-ui-btn tegaki-icon tegaki-up-open';
    $T.on(el, 'click', Tegaki.onMoveLayerClick);
    row.appendChild(el);
    
    el = $T.el('span');
    el.id = 'tegaki-layer-down';
    el.title = TegakiStrings.moveLayerDown;
    el.className = 'tegaki-ui-btn tegaki-icon tegaki-down-open';
    $T.on(el, 'click', Tegaki.onMoveLayerClick);
    row.appendChild(el);
    
    ctrl.appendChild(row);
    
    return ctrl;
  },
  
  buildSizeCtrlGroup: function() {
    var el, ctrl, row;
    
    ctrl = this.buildCtrlGroup('size', TegakiStrings.size);
    
    row = $T.el('div');
    row.className = 'tegaki-ctrlrow';
    
    el = $T.el('input');
    el.id = 'tegaki-size';
    el.className = 'tegaki-ctrl-range';
    el.min = 1;
    el.max = Tegaki.maxSize;
    el.type = 'range';
    el.title = TegakiKeybinds.getCaption('toolSize');
    $T.on(el, 'input', Tegaki.onToolSizeChange);
    row.appendChild(el);
    
    el = $T.el('input');
    el.id = 'tegaki-size-lbl';
    el.setAttribute('maxlength', 3);
    el.className = 'tegaki-stealth-input tegaki-range-lbl';
    $T.on(el, 'input', Tegaki.onToolSizeChange);
    row.appendChild(el);
    
    ctrl.appendChild(row);
    
    return ctrl;
  },
  
  buildAlphaCtrlGroup: function() {
    var el, ctrl, row;
    
    ctrl = this.buildCtrlGroup('alpha', TegakiStrings.alpha);
    
    row = $T.el('div');
    row.className = 'tegaki-ctrlrow';
    
    el = $T.el('input');
    el.id = 'tegaki-alpha';
    el.className = 'tegaki-ctrl-range';
    el.min = 0;
    el.max = 100;
    el.step = 1;
    el.type = 'range';
    $T.on(el, 'input', Tegaki.onToolAlphaChange);
    row.appendChild(el);
    
    el = $T.el('input');
    el.id = 'tegaki-alpha-lbl';
    el.setAttribute('maxlength', 3);
    el.className = 'tegaki-stealth-input tegaki-range-lbl';
    $T.on(el, 'input', Tegaki.onToolAlphaChange);
    row.appendChild(el);
    
    ctrl.appendChild(row);
    
    return ctrl;
  },
  
  buildFlowCtrlGroup: function() {
    var el, ctrl, row;
    
    ctrl = this.buildCtrlGroup('flow', TegakiStrings.flow);
    
    row = $T.el('div');
    row.className = 'tegaki-ctrlrow';
    
    el = $T.el('input');
    el.id = 'tegaki-flow';
    el.className = 'tegaki-ctrl-range';
    el.min = 0;
    el.max = 100;
    el.step = 1;
    el.type = 'range';
    $T.on(el, 'input', Tegaki.onToolFlowChange);
    row.appendChild(el);
    
    el = $T.el('input');
    el.id = 'tegaki-flow-lbl';
    el.setAttribute('maxlength', 3);
    el.className = 'tegaki-stealth-input tegaki-range-lbl';
    $T.on(el, 'input', Tegaki.onToolFlowChange);
    row.appendChild(el);
    
    ctrl.appendChild(row);
    
    return ctrl;
  },
  
  buildZoomCtrlGroup: function() {
    var el, btn, ctrl;
    
    ctrl = this.buildCtrlGroup('zoom', TegakiStrings.zoom);
    
    btn = $T.el('div');
    btn.className = 'tegaki-ui-btn tegaki-icon tegaki-plus';
    btn.id = 'tegaki-zoomin-btn';
    btn.setAttribute('data-in', 1);
    $T.on(btn, 'click', Tegaki.onZoomChange);
    ctrl.appendChild(btn);
    
    btn = $T.el('div');
    btn.className = 'tegaki-ui-btn tegaki-icon tegaki-minus';
    btn.id = 'tegaki-zoomout-btn';
    btn.setAttribute('data-out', 1);
    $T.on(btn, 'click', Tegaki.onZoomChange);
    ctrl.appendChild(btn);
    
    el = $T.el('div');
    el.id = 'tegaki-zoom-lbl';
    ctrl.appendChild(el);
    
    return ctrl;
  },
  
  buildColorCtrlGroup: function(mainColor) {
    var el, cnt, btn, ctrl, color, edge, i, palette, cls;
    
    edge = / Edge\//i.test(window.navigator.userAgent);
    
    ctrl = this.buildCtrlGroup('color', TegakiStrings.color);
    
    cnt = $T.el('div');
    cnt.id = 'tegaki-color-ctrl';
    
    el = $T.el('div');
    el.id = 'tegaki-color';
    edge && el.classList.add('tegaki-hidden');
    el.style.backgroundColor = mainColor;
    $T.on(el, 'mousedown', Tegaki.onMainColorClick);
    cnt.appendChild(el);
    
    el = $T.el('div');
    el.id = 'tegaki-palette-switcher';
    
    btn = $T.el('span');
    btn.id = 'tegaki-palette-prev-btn';
    btn.title = TegakiStrings.switchPalette;
    btn.setAttribute('data-prev', '1');
    btn.className = 'tegaki-ui-btn tegaki-icon tegaki-left-open tegaki-disabled';
    $T.on(btn, 'click', Tegaki.onSwitchPaletteClick);
    el.appendChild(btn);
    
    btn = $T.el('span');
    btn.id = 'tegaki-palette-next-btn';
    btn.title = TegakiStrings.switchPalette;
    btn.className = 'tegaki-ui-btn tegaki-icon tegaki-right-open';
    $T.on(btn, 'click', Tegaki.onSwitchPaletteClick);
    el.appendChild(btn);
    
    cnt.appendChild(el);
    
    ctrl.appendChild(cnt);
    
    cnt = $T.el('div');
    cnt.id = 'tegaki-color-grids';
    
    for (i = 0; i < TegakiColorPalettes.length; ++i) {
      el = $T.el('div');
      
      el.setAttribute('data-id', i);
      
      cls = 'tegaki-color-grid';
      
      palette = TegakiColorPalettes[i];
      
      if (palette.length <= 18) {
        cls += ' tegaki-color-grid-20';
      }
      else {
        cls += ' tegaki-color-grid-15';
      }
      
      if (i > 0) {
        cls += ' tegaki-hidden';
      }
      
      el.className = cls;
      
      for (color of palette) {
        btn = $T.el('div');
        btn.title = TegakiStrings.paletteSlotReplace;
        btn.className = 'tegaki-color-btn';
        btn.setAttribute('data-color', color);
        btn.style.backgroundColor = color;
        $T.on(btn, 'mousedown', Tegaki.onPaletteColorClick);
        el.appendChild(btn);
      }
      
      cnt.appendChild(el);
    }
    
    ctrl.appendChild(cnt);
    
    el = $T.el('input');
    el.id = 'tegaki-colorpicker';
    !edge && el.classList.add('tegaki-hidden');
    el.value = color;
    el.type = 'color';
    $T.on(el, 'change', Tegaki.onColorPicked);
    
    ctrl.appendChild(el);
    
    return ctrl;
  },
  
  buildStatusCnt: function() {
    var cnt, el;
    
    cnt = $T.el('div');
    cnt.id = 'tegaki-status-cnt';
    
    if (Tegaki.saveReplay) {
      el = $T.el('div');
      el.id = 'tegaki-status-replay';
      el.textContent = '⬤';
      el.setAttribute('title', TegakiStrings.recordingEnabled);
      cnt.appendChild(el);
    }
    
    el = $T.el('div');
    el.id = 'tegaki-status-output';
    cnt.appendChild(el);
    
    el = $T.el('div');
    el.id = 'tegaki-status-version';
    el.textContent = 'tegaki.js v' + Tegaki.VERSION;
    cnt.appendChild(el);
    
    return cnt;
  },
  
  buildReplayControls: function() {
    var cnt, btn, el;
    
    cnt = $T.el('div');
    cnt.id = 'tegaki-replay-controls';
    cnt.className = 'tegaki-hidden';
    
    btn = $T.el('span');
    btn.id = 'tegaki-replay-gapless-btn';
    btn.className = 'tegaki-ui-cb-w';
    $T.on(btn, 'click', Tegaki.onReplayGaplessClick);
    
    el = $T.el('span');
    el.id = 'tegaki-replay-gapless-cb';
    el.className = 'tegaki-ui-cb';
    btn.appendChild(el);
    
    el = $T.el('span');
    el.className = 'tegaki-menu-lbl';
    el.textContent = TegakiStrings.gapless;
    btn.appendChild(el);
    
    cnt.appendChild(btn);
    
    btn = $T.el('span');
    btn.id = 'tegaki-replay-play-btn';
    btn.className = 'tegaki-ui-btn tegaki-icon tegaki-play';
    btn.setAttribute('title', TegakiStrings.play);
    $T.on(btn, 'click', Tegaki.onReplayPlayPauseClick);
    cnt.appendChild(btn);
    
    btn = $T.el('span');
    btn.className = 'tegaki-ui-btn tegaki-icon tegaki-to-start';
    btn.setAttribute('title', TegakiStrings.rewind);
    $T.on(btn, 'click', Tegaki.onReplayRewindClick);
    cnt.appendChild(btn);
    
    btn = $T.el('span');
    btn.id = 'tegaki-replay-slower-btn';
    btn.className = 'tegaki-ui-btn tegaki-icon tegaki-fast-bw';
    btn.setAttribute('title', TegakiStrings.slower);
    $T.on(btn, 'click', Tegaki.onReplaySlowDownClick);
    cnt.appendChild(btn);
    
    el = $T.el('span');
    el.id = 'tegaki-replay-speed-lbl';
    el.className = 'tegaki-menu-lbl';
    el.textContent = '1.0';
    cnt.appendChild(el);
    
    btn = $T.el('span');
    btn.id = 'tegaki-replay-faster-btn';
    btn.className = 'tegaki-ui-btn tegaki-icon tegaki-fast-fw';
    btn.setAttribute('title', TegakiStrings.faster);
    $T.on(btn, 'click', Tegaki.onReplaySpeedUpClick);
    cnt.appendChild(btn);
    
    el = $T.el('span');
    el.id = 'tegaki-replay-now-lbl';
    el.className = 'tegaki-menu-lbl';
    el.textContent = '00:00';
    cnt.appendChild(el);
    
    el = $T.el('span');
    el.id = 'tegaki-replay-end-lbl';
    el.className = 'tegaki-menu-lbl';
    el.textContent = '00:00';
    cnt.appendChild(el);
    
    return cnt;
  },
  
  buildLayerGridCell: function(layer) {
    var cnt, el, cell;
    
    cnt = $T.el('div');
    cnt.id = 'tegaki-layers-cell-' + layer.id;
    cnt.className = 'tegaki-layers-cell';
    cnt.setAttribute('data-id', layer.id);
    cnt.draggable = true;
    cnt.setAttribute('data-id', layer.id);
    
    $T.on(cnt, 'pointerdown', TegakiUI.onLayerSelectorPtrDown);
    $T.on(cnt, 'pointerup', Tegaki.onLayerSelectorClick);
    
    $T.on(cnt, 'dragstart', TegakiUI.onLayerDragStart);
    $T.on(cnt, 'dragover', TegakiUI.onLayerDragOver);
    $T.on(cnt, 'drop', TegakiUI.onLayerDragDrop);
    $T.on(cnt, 'dragend', TegakiUI.onLayerDragEnd);
    $T.on(cnt, 'dragleave', TegakiUI.onLayerDragLeave);
    $T.on(cnt, 'dragexit', TegakiUI.onLayerDragLeave);
    
    // visibility toggle
    cell = $T.el('div');
    cell.className = 'tegaki-layers-cell-v';
    
    el = $T.el('span');
    el.id = 'tegaki-layers-cb-v-' + layer.id;
    el.className = 'tegaki-ui-cb';
    el.setAttribute('data-id', layer.id);
    el.title = TegakiStrings.toggleVisibility;
    $T.on(el, 'click', Tegaki.onLayerToggleVisibilityClick);
    
    if (layer.visible) {
      el.className += ' tegaki-ui-cb-a';
    }
    
    cell.appendChild(el);
    cnt.appendChild(cell);
    
    // preview
    cell = $T.el('div');
    cell.className = 'tegaki-layers-cell-p';
    
    el = $T.el('canvas');
    el.id = 'tegaki-layers-p-canvas-' + layer.id;
    el.className = 'tegaki-alpha-bg-xs';
    [el.width, el.height] = TegakiUI.getLayerPreviewSize(); 
    
    cell.appendChild(el);
    cnt.appendChild(cell);
    
    // name
    cell = $T.el('div');
    cell.className = 'tegaki-layers-cell-n';
    
    el = $T.el('div');
    el.id = 'tegaki-layer-name-' + layer.id;
    el.className = 'tegaki-ellipsis';
    el.setAttribute('data-id', layer.id);
    el.textContent = layer.name;
    $T.on(el, 'dblclick', Tegaki.onLayerNameChangeClick);
    
    cell.appendChild(el);
    cnt.appendChild(cell);
    
    return cnt;
  },
  
  // ---
  
  onLayerSelectorPtrDown: function(e) {
    if (e.pointerType === 'mouse') {
      if (this.hasAttribute('data-nodrag')) {
        this.removeAttribute('data-nodrag');
        $T.on(this, 'dragstart', TegakiUI.onLayerDragStart);
      }
    }
    else if (!this.hasAttribute('data-nodrag')) {
      this.setAttribute('data-nodrag', 1);
      $T.off(this, 'dragstart', TegakiUI.onLayerDragStart);
    }
  },
  
  onLayerDragStart: function(e) {
    var el, id;
    
    if (e.ctrlKey) {
      return;
    }
    
    TegakiUI.draggedNode = null;
    
    if (!$T.id('tegaki-layers-grid').children[1]) {
      e.preventDefault();
      return;
    }
    
    id = +e.target.getAttribute('data-id');
    
    el = $T.el('div');
    el.className = 'tegaki-invis';
    e.dataTransfer.setDragImage(el, 0, 0);
    e.dataTransfer.setData('text/plain', id);
    e.dataTransfer.effectAllowed = 'move';
    
    TegakiUI.draggedNode = e.target;
    
    TegakiUI.updateLayersGridDragExt(true);
  },
  
  onLayerDragOver: function(e) {
    e.preventDefault();
    
    e.dataTransfer.dropEffect = 'move';
    
    TegakiUI.updateLayersGridDragEffect(
      e.target,
      +TegakiUI.draggedNode.getAttribute('data-id')
    );
  },
  
  onLayerDragLeave: function(e) {
    TegakiUI.updateLayersGridDragEffect();
  },
  
  onLayerDragEnd: function(e) {
    TegakiUI.draggedNode = null;
    TegakiUI.updateLayersGridDragExt(false);
    TegakiUI.updateLayersGridDragEffect();
  },
  
  onLayerDragDrop: function(e) {
    var tgtId, srcId, belowPos;
    
    e.preventDefault();
    
    TegakiUI.draggedNode = null;
    
    [tgtId] = TegakiUI.layersGridFindDropTgt(e.target);
    srcId = +e.dataTransfer.getData('text/plain');
    
    TegakiUI.updateLayersGridDragEffect(e.target.parentNode);
    TegakiUI.updateLayersGridDragExt(false);
    
    if (!TegakiUI.layersGridCanDrop(tgtId, srcId)) {
      return;
    }
    
    if (!tgtId) {
      belowPos = Tegaki.layers.length;
    }
    else {
      belowPos = TegakiLayers.getLayerPosById(tgtId);
    }
    
    if (!TegakiLayers.selectedLayersHas(srcId)) {
      Tegaki.setActiveLayer(srcId);
    }
    
    Tegaki.moveSelectedLayers(belowPos);
  },
  
  updateLayersGridDragExt: function(flag) {
    var cnt, el;
    
    cnt = $T.id('tegaki-layers-grid');
    
    if (!cnt.children[1]) {
      return;
    }
    
    if (flag) {
      el = $T.el('div');
      el.id = 'tegaki-layers-cell-dx';
      el.draggable = true;
      $T.on(el, 'dragover', TegakiUI.onLayerDragOver);
      $T.on(el, 'drop', TegakiUI.onLayerDragDrop);
      cnt.parentNode.insertBefore(el, cnt);
    }
    else {
      if (el = $T.id('tegaki-layers-cell-dx')) {
        el.parentNode.removeChild(el);
      }
    }
  },
  
  updateLayersGridDragEffect: function(tgt, srcId) {
    var el, nodes, tgtId;
    
    nodes = $T.cls('tegaki-layers-cell-d', $T.id('tegaki-ctrlgrp-layers'));
    
    for (el of nodes) {
      el.classList.remove('tegaki-layers-cell-d');
    }
    
    if (!tgt || !srcId) {
      return;
    }
    
    [tgtId, tgt] = TegakiUI.layersGridFindDropTgt(tgt);
    
    if (!TegakiUI.layersGridCanDrop(tgtId, srcId)) {
      return;
    }
    
    if (!tgt) {
      tgt = $T.id('tegaki-layers-grid');
    }
    
    tgt.classList.add('tegaki-layers-cell-d');
  },
  
  layersGridFindDropTgt: function(tgt) {
    var tgtId, cnt;
    
    tgtId = +tgt.getAttribute('data-id');
    
    cnt = $T.id('tegaki-ctrlgrp-layers');
    
    while (!tgt.draggable && tgt !== cnt) {
      tgt = tgt.parentNode;
      tgtId = +tgt.getAttribute('data-id');
    }
    
    if (tgt === cnt || !tgt.draggable) {
      return [0, null];
    }
    
    return [tgtId, tgt];
  },
  
  layersGridCanDrop: function(tgtId, srcId) {
    var srcEl;
    
    if (tgtId === srcId) {
      return false;
    }
    
    srcEl = $T.id('tegaki-layers-cell-' + srcId);
    
    if (!srcEl.previousElementSibling) {
      if (!tgtId) {
        return false;
      }
    }
    else if (+srcEl.previousElementSibling.getAttribute('data-id') === tgtId) {
      return false;
    }
    
    return true;
  },
  
  // ---
  
  setReplayMode: function(flag) {
    Tegaki.bg.classList[flag ? 'add' : 'remove']('tegaki-replay-mode');
  },
  
  // ---
  
  onToolChanged: function() {
    $T.id('tegaki-toolmode-bar').classList.remove('tegaki-hidden');
    TegakiUI.updateToolSize();
    TegakiUI.updateToolAlpha();
    TegakiUI.updateToolFlow();
    TegakiUI.updateToolModes();
  },
  
  // ---
  
  updateLayerAlphaOpt: function() {
    var el = $T.id('tegaki-layer-alpha-opt');
    el.value = Math.round(Tegaki.activeLayer.alpha * 100);
  },
  
  updateLayerName: function(layer) {
    var el;
    
    if (el = $T.id('tegaki-layer-name-' + layer.id)) {
      el.textContent = layer.name;
    }
  },
  
  updateLayerPreview: function(layer) {
    var canvas, ctx;
    
    canvas = $T.id('tegaki-layers-p-canvas-' + layer.id);
    
    if (!canvas) {
      return;
    }
    
    ctx = TegakiUI.getLayerPreviewCtx(layer);
    
    if (!ctx) {
      ctx = canvas.getContext('2d');
      ctx.imageSmoothingEnabled = false;
      TegakiUI.setLayerPreviewCtx(layer, ctx);
    }
    
    $T.clearCtx(ctx);
    ctx.drawImage(layer.canvas, 0, 0, canvas.width, canvas.height);
  },
  
  updateLayerPreviewSize: function(regen) {
    var el, layer, size;
    
    size = TegakiUI.getLayerPreviewSize();
    
    for (layer of Tegaki.layers) {
      if (el = $T.id('tegaki-layers-p-canvas-' + layer.id)) {
        [el.width, el.height] = size;
        
        if (regen) {
          TegakiUI.updateLayerPreview(layer);
        }
      }
    }
  },
  
  getLayerPreviewCtx: function(layer) {
    TegakiUI.layerPreviewCtxCache.get(layer);
  },
  
  setLayerPreviewCtx: function(layer, ctx) {
    TegakiUI.layerPreviewCtxCache.set(layer, ctx);
  },
  
  deleteLayerPreviewCtx: function(layer) {
    TegakiUI.layerPreviewCtxCache.delete(layer);
  },
  
  updateLayersGridClear: function() {
    $T.id('tegaki-layers-grid').innerHTML = '';
  },
  
  updateLayersGrid: function() {
    var layer, el, frag, cnt;
    
    frag = $T.frag();
    
    for (layer of Tegaki.layers) {
      el = TegakiUI.buildLayerGridCell(layer);
      frag.insertBefore(el, frag.firstElementChild);
    }
    
    TegakiUI.updateLayersGridClear();
    
    cnt.appendChild(frag);
  },
  
  updateLayersGridActive: function(layerId) {
    var el;
    
    el = $T.cls('tegaki-layers-cell-a', $T.id('tegaki-layers-grid'))[0];
    
    if (el) {
      el.classList.remove('tegaki-layers-cell-a');
    }
    
    el = $T.id('tegaki-layers-cell-' + layerId);
    
    if (el) {
      el.classList.add('tegaki-layers-cell-a');
    }
    
    TegakiUI.updateLayerAlphaOpt();
  },
  
  updateLayersGridAdd: function(layer, aboveId) {
    var el, cnt, ref;
    
    el = TegakiUI.buildLayerGridCell(layer);
    
    cnt = $T.id('tegaki-layers-grid');
    
    if (aboveId) {
      ref = $T.id('tegaki-layers-cell-' + aboveId);
    }
    else {
      ref = null;
    }
    
    cnt.insertBefore(el, ref);
  },
  
  updateLayersGridRemove: function(id) {
    var el;
    
    if (el = $T.id('tegaki-layers-cell-' + id)) {
      el.parentNode.removeChild(el);
    }
  },
  
  updayeLayersGridOrder: function() {
    var layer, cnt, el;
    
    cnt = $T.id('tegaki-layers-grid');
    
    for (layer of Tegaki.layers) {
      el = $T.id('tegaki-layers-cell-' + layer.id);
      cnt.insertBefore(el, cnt.firstElementChild);
    }
  },
  
  updateLayersGridVisibility: function(id, flag) {
    var el;
    
    el = $T.id('tegaki-layers-cb-v-' + id);
    
    if (!el) {
      return;
    }
    
    if (flag) {
      el.classList.add('tegaki-ui-cb-a');
    }
    else {
      el.classList.remove('tegaki-ui-cb-a');
    }
  },
  
  updateLayersGridSelectedClear: function() {
    var layer, el;
    
    for (layer of Tegaki.layers) {
      if (el = $T.id('tegaki-layers-cell-' + layer.id)) {
        el.classList.remove('tegaki-layers-cell-s');
      }
    }
  },
  
  updateLayersGridSelectedSet: function(id, flag) {
    var el;
    
    if (el = $T.id('tegaki-layers-cell-' + id)) {
      if (flag) {
        el.classList.add('tegaki-layers-cell-s');
      }
      else {
        el.classList.remove('tegaki-layers-cell-s');
      }
    }
  },
  
  updateToolSize: function() {
    var el = $T.id('tegaki-ctrlgrp-size');
    
    if (Tegaki.tool.useSize) {
      el.classList.remove('tegaki-hidden');
      
      $T.id('tegaki-size-lbl').value = Tegaki.tool.size;
      $T.id('tegaki-size').value = Tegaki.tool.size;
    }
    else {
      el.classList.add('tegaki-hidden');
    }
  },
  
  updateToolAlpha: function() {
    var val, el = $T.id('tegaki-ctrlgrp-alpha');
    
    if (Tegaki.tool.useAlpha) {
      el.classList.remove('tegaki-hidden');
      
      val = Math.round(Tegaki.tool.alpha * 100);
      $T.id('tegaki-alpha-lbl').value = val;
      $T.id('tegaki-alpha').value = val;
    }
    else {
      el.classList.add('tegaki-hidden');
    }
  },
  
  updateToolFlow: function() {
    var val, el = $T.id('tegaki-ctrlgrp-flow');
    
    if (Tegaki.tool.useFlow) {
      el.classList.remove('tegaki-hidden');
      
      val = Math.round(Tegaki.tool.flow * 100);
      $T.id('tegaki-flow-lbl').value = val;
      $T.id('tegaki-flow').value = val;
    }
    else {
      el.classList.add('tegaki-hidden');
    }
  },
  
  updateToolDynamics: function() {
    var ctrl, cb;
    
    ctrl = $T.id('tegaki-tool-mode-dynamics');
    
    if (!Tegaki.tool.usesDynamics()) {
      ctrl.classList.add('tegaki-hidden');
    }
    else {
      cb = $T.id('tegaki-tool-mode-dynamics-size');
      
      if (Tegaki.tool.useSizeDynamics) {
        if (Tegaki.tool.sizeDynamicsEnabled) {
          cb.classList.add('tegaki-sw-btn-a');
        }
        else {
          cb.classList.remove('tegaki-sw-btn-a');
        }
        
        cb.classList.remove('tegaki-hidden');
      }
      else {
        cb.classList.add('tegaki-hidden');
      }
      
      cb = $T.id('tegaki-tool-mode-dynamics-alpha');
      
      if (Tegaki.tool.useAlphaDynamics) {
        if (Tegaki.tool.alphaDynamicsEnabled) {
          cb.classList.add('tegaki-sw-btn-a');
        }
        else {
          cb.classList.remove('tegaki-sw-btn-a');
        }
        
        cb.classList.remove('tegaki-hidden');
      }
      else {
        cb.classList.add('tegaki-hidden');
      }
      
      cb = $T.id('tegaki-tool-mode-dynamics-flow');
      
      if (Tegaki.tool.useFlowDynamics) {
        if (Tegaki.tool.flowDynamicsEnabled) {
          cb.classList.add('tegaki-sw-btn-a');
        }
        else {
          cb.classList.remove('tegaki-sw-btn-a');
        }
        
        cb.classList.remove('tegaki-hidden');
      }
      else {
        cb.classList.add('tegaki-hidden');
      }
      
      ctrl.classList.remove('tegaki-hidden');
    }
  },
  
  updateToolShape: function() {
    var tipId, ctrl, cnt, btn, tipList;
    
    ctrl = $T.id('tegaki-tool-mode-tip');
    
    if (!Tegaki.tool.tipList) {
      ctrl.classList.add('tegaki-hidden');
    }
    else {
      tipList = Tegaki.tool.tipList;
      
      cnt = $T.id('tegaki-tool-mode-tip-ctrl');
      
      cnt.innerHTML = '';
      
      for (tipId = 0; tipId < tipList.length; ++tipId) {
        btn = $T.el('span');
        btn.id = 'tegaki-tool-mode-tip-' + tipId;
        btn.className = 'tegaki-sw-btn';
        btn.setAttribute('data-id', tipId);
        btn.textContent = TegakiStrings[tipList[tipId]];
        
        $T.on(btn, 'mousedown', Tegaki.onToolTipClick);
        
        cnt.appendChild(btn);
        
        if (Tegaki.tool.tipId === tipId) {
          btn.classList.add('tegaki-sw-btn-a');
        }
      }
      
      ctrl.classList.remove('tegaki-hidden');
    }
  },
  
  updateToolPreserveAlpha: function() {
    var cb, ctrl;
    
    ctrl = $T.id('tegaki-tool-mode-mask');
    
    if (!Tegaki.tool.usePreserveAlpha) {
      ctrl.classList.add('tegaki-hidden');
    }
    else {
      cb = $T.id('tegaki-tool-mode-mask-alpha');
      
      if (Tegaki.tool.preserveAlphaEnabled) {
        cb.classList.add('tegaki-sw-btn-a');
      }
      else {
        cb.classList.remove('tegaki-sw-btn-a');
      }
      
      ctrl.classList.remove('tegaki-hidden');
    }
  },
  
  updateToolModes: function() {
    var el, flag;
    
    TegakiUI.updateToolShape();
    TegakiUI.updateToolDynamics();
    TegakiUI.updateToolPreserveAlpha();
    
    flag = false;
    
    for (el of $T.id('tegaki-toolmode-bar').children) {
      if (!flag && !el.classList.contains('tegaki-hidden')) {
        el.classList.add('tegaki-ui-borderless');
        flag = true;
      }
      else {
        el.classList.remove('tegaki-ui-borderless');
      }
    }
  },
  
  updateUndoRedo: function(undoSize, redoSize) {
    var u, r;
    
    if (Tegaki.replayMode) {
      return;
    }
    
    u = $T.id('tegaki-undo-btn').classList;
    r = $T.id('tegaki-redo-btn').classList;
    
    if (undoSize) {
      if (u.contains('tegaki-disabled')) {
        u.remove('tegaki-disabled');
      }
    }
    else {
      if (!u.contains('tegaki-disabled')) {
        u.add('tegaki-disabled');
      }
    }
    
    if (redoSize) {
      if (r.contains('tegaki-disabled')) {
        r.remove('tegaki-disabled');
      }
    }
    else {
      if (!r.contains('tegaki-disabled')) {
        r.add('tegaki-disabled');
      }
    }
  },
  
  updateZoomLevel: function() {
    $T.id('tegaki-zoom-lbl').textContent = (Tegaki.zoomFactor * 100) + '%';
    
    if (Tegaki.zoomLevel + Tegaki.zoomBaseLevel >= Tegaki.zoomFactorList.length) {
      $T.id('tegaki-zoomin-btn').classList.add('tegaki-disabled');
    }
    else {
      $T.id('tegaki-zoomin-btn').classList.remove('tegaki-disabled');
    }
    
    if (Tegaki.zoomLevel + Tegaki.zoomBaseLevel <= 0) {
      $T.id('tegaki-zoomout-btn').classList.add('tegaki-disabled');
    }
    else {
      $T.id('tegaki-zoomout-btn').classList.remove('tegaki-disabled');
    }
  },
  
  updateColorPalette: function() {
    var el, nodes, id;
    
    id = Tegaki.colorPaletteId;
    
    nodes = $T.cls('tegaki-color-grid', $T.id('tegaki-color-grids'));
    
    for (el of nodes) {
      if (+el.getAttribute('data-id') === id) {
        el.classList.remove('tegaki-hidden');
      }
      else {
        el.classList.add('tegaki-hidden');
      }
    }
    
    el = $T.id('tegaki-palette-prev-btn');
    
    if (id === 0) {
      el.classList.add('tegaki-disabled');
    }
    else {
      el.classList.remove('tegaki-disabled');
    }
    
    el = $T.id('tegaki-palette-next-btn');
    
    if (id === TegakiColorPalettes.length - 1) {
      el.classList.add('tegaki-disabled');
    }
    else {
      el.classList.remove('tegaki-disabled');
    }
  },
  
  updateReplayTime: function(full) {
    var now, end, r = Tegaki.replayViewer;
    
    now = r.getCurrentPos();
    
    end = r.getDuration();
    
    if (now > end) {
      now = end;
    }
    
    $T.id('tegaki-replay-now-lbl').textContent = $T.msToHms(now);
    
    if (full) {
      $T.id('tegaki-replay-end-lbl').textContent = $T.msToHms(end);
    }
  },
  
  updateReplayControls: function() {
    TegakiUI.updateReplayGapless();
    TegakiUI.updateReplayPlayPause();
    TegakiUI.updateReplaySpeed();
  },
  
  updateReplayGapless: function() {
    var el, r = Tegaki.replayViewer;
    
    el = $T.id('tegaki-replay-gapless-cb');
    
    if (r.gapless) {
      el.classList.add('tegaki-ui-cb-a');
    }
    else {
      el.classList.remove('tegaki-ui-cb-a');
    }
  },
  
  updateReplayPlayPause: function() {
    var el, r = Tegaki.replayViewer;
    
    el = $T.id('tegaki-replay-play-btn');
    
    if (r.playing) {
      el.classList.remove('tegaki-play');
      el.classList.add('tegaki-pause');
      el.setAttribute('title', TegakiStrings.pause);
    }
    else {
      el.classList.add('tegaki-play');
      el.classList.remove('tegaki-pause');
      el.setAttribute('title', TegakiStrings.play);
      
      if (r.getCurrentPos() < r.getDuration()) {
        el.classList.remove('tegaki-disabled');
      }
      else {
        el.classList.add('tegaki-disabled');
      }
    }
  },
  
  updateReplaySpeed: function() {
    var el, r = Tegaki.replayViewer;
    
    $T.id('tegaki-replay-speed-lbl').textContent = r.speed.toFixed(1);
    
    el = $T.id('tegaki-replay-slower-btn');
    
    if (r.speedIndex === 0) {
      el.classList.add('tegaki-disabled');
    }
    else {
      el.classList.remove('tegaki-disabled');
    }
    
    el = $T.id('tegaki-replay-faster-btn');
    
    if (r.speedIndex === r.speedList.length - 1) {
      el.classList.add('tegaki-disabled');
    }
    else {
      el.classList.remove('tegaki-disabled');
    }
  },
  
  enableReplayControls: function(flag) {
    if (flag) {
      $T.id('tegaki-replay-controls').classList.remove('tegaki-hidden');
    }
    else {
      $T.id('tegaki-replay-controls').classList.add('tegaki-hidden');
    }
  },
  
  setRecordingStatus: function(flag) {
    var el = $T.id('tegaki-status-replay');
    
    if (flag) {
      el.classList.remove('tegaki-hidden');
    }
    else {
      el.classList.add('tegaki-hidden');
    }
  }
};
/*! UZIP.js, © 2018 Photopea, MIT License */

var UZIP = {};
if(typeof module == "object") module.exports = UZIP;

UZIP.inflateRaw = function(file, buf) {  return UZIP.F.inflate(file, buf);  }

UZIP.deflateRaw = function(data, opts) {
  if(opts==null) opts={level:6};
  var buf=new Uint8Array(50+Math.floor(data.length*1.1));
  var off = UZIP.F.deflateRaw(data, buf, off, opts.level);
  return new Uint8Array(buf.buffer, 0, off);
}

UZIP.bin = {
  readUshort : function(buff,p)  {  return (buff[p]) | (buff[p+1]<<8);  },
  writeUshort: function(buff,p,n){  buff[p] = (n)&255;  buff[p+1] = (n>>8)&255;  },
  readUint   : function(buff,p)  {  return (buff[p+3]*(256*256*256)) + ((buff[p+2]<<16) | (buff[p+1]<< 8) | buff[p]);  },
  writeUint  : function(buff,p,n){  buff[p]=n&255;  buff[p+1]=(n>>8)&255;  buff[p+2]=(n>>16)&255;  buff[p+3]=(n>>24)&255;  },
  readASCII  : function(buff,p,l){  var s = "";  for(var i=0; i<l; i++) s += String.fromCharCode(buff[p+i]);  return s;    },
  writeASCII : function(data,p,s){  for(var i=0; i<s.length; i++) data[p+i] = s.charCodeAt(i);  },
  pad : function(n) { return n.length < 2 ? "0" + n : n; },
  readUTF8 : function(buff, p, l) {
    var s = "", ns;
    for(var i=0; i<l; i++) s += "%" + UZIP.bin.pad(buff[p+i].toString(16));
    try {  ns = decodeURIComponent(s); }
    catch(e) {  return UZIP.bin.readASCII(buff, p, l);  }
    return  ns;
  },
  writeUTF8 : function(buff, p, str) {
    var strl = str.length, i=0;
    for(var ci=0; ci<strl; ci++)
    {
      var code = str.charCodeAt(ci);
      if     ((code&(0xffffffff-(1<< 7)+1))==0) {  buff[p+i] = (     code     );  i++;  }
      else if((code&(0xffffffff-(1<<11)+1))==0) {  buff[p+i] = (192|(code>> 6));  buff[p+i+1] = (128|((code>> 0)&63));  i+=2;  }
      else if((code&(0xffffffff-(1<<16)+1))==0) {  buff[p+i] = (224|(code>>12));  buff[p+i+1] = (128|((code>> 6)&63));  buff[p+i+2] = (128|((code>>0)&63));  i+=3;  }
      else if((code&(0xffffffff-(1<<21)+1))==0) {  buff[p+i] = (240|(code>>18));  buff[p+i+1] = (128|((code>>12)&63));  buff[p+i+2] = (128|((code>>6)&63));  buff[p+i+3] = (128|((code>>0)&63)); i+=4;  }
      else throw "e";
    }
    return i;
  },
  sizeUTF8 : function(str) {
    var strl = str.length, i=0;
    for(var ci=0; ci<strl; ci++)
    {
      var code = str.charCodeAt(ci);
      if     ((code&(0xffffffff-(1<< 7)+1))==0) {  i++ ;  }
      else if((code&(0xffffffff-(1<<11)+1))==0) {  i+=2;  }
      else if((code&(0xffffffff-(1<<16)+1))==0) {  i+=3;  }
      else if((code&(0xffffffff-(1<<21)+1))==0) {  i+=4;  }
      else throw "e";
    }
    return i;
  }
}







UZIP.F = {};

UZIP.F.deflateRaw = function(data, out, opos, lvl) {  
  var opts = [
  /*
     ush good_length; /* reduce lazy search above this match length 
     ush max_lazy;    /* do not perform lazy search above this match length 
         ush nice_length; /* quit search above this match length 
  */
  /*      good lazy nice chain */
  /* 0 */ [ 0,   0,   0,    0,0],  /* store only */
  /* 1 */ [ 4,   4,   8,    4,0], /* max speed, no lazy matches */
  /* 2 */ [ 4,   5,  16,    8,0],
  /* 3 */ [ 4,   6,  16,   16,0],

  /* 4 */ [ 4,  10,  16,   32,0],  /* lazy matches */
  /* 5 */ [ 8,  16,  32,   32,0],
  /* 6 */ [ 8,  16, 128,  128,0],
  /* 7 */ [ 8,  32, 128,  256,0],
  /* 8 */ [32, 128, 258, 1024,1],
  /* 9 */ [32, 258, 258, 4096,1]]; /* max compression */
  
  var opt = opts[lvl];
  
  
  var U = UZIP.F.U, goodIndex = UZIP.F._goodIndex, hash = UZIP.F._hash, putsE = UZIP.F._putsE;
  var i = 0, pos = opos<<3, cvrd = 0, dlen = data.length;
  
  if(lvl==0) {
    while(i<dlen) {   var len = Math.min(0xffff, dlen-i);
      putsE(out, pos, (i+len==dlen ? 1 : 0));  pos = UZIP.F._copyExact(data, i, len, out, pos+8);  i += len;  }
    return pos>>>3;
  }

  var lits = U.lits, strt=U.strt, prev=U.prev, li=0, lc=0, bs=0, ebits=0, c=0, nc=0;  // last_item, literal_count, block_start
  if(dlen>2) {  nc=UZIP.F._hash(data,0);  strt[nc]=0;  }
  var nmch=0,nmci=0;
  
  for(i=0; i<dlen; i++)  {
    c = nc;
    //*
    if(i+1<dlen-2) {
      nc = UZIP.F._hash(data, i+1);
      var ii = ((i+1)&0x7fff);
      prev[ii]=strt[nc];
      strt[nc]=ii;
    } //*/
    if(cvrd<=i) {
      if((li>14000 || lc>26697) && (dlen-i)>100) {
        if(cvrd<i) {  lits[li]=i-cvrd;  li+=2;  cvrd=i;  }
        pos = UZIP.F._writeBlock(((i==dlen-1) || (cvrd==dlen))?1:0, lits, li, ebits, data,bs,i-bs, out, pos);  li=lc=ebits=0;  bs=i;
      }
      
      var mch = 0;
      //if(nmci==i) mch= nmch;  else 
      if(i<dlen-2) mch = UZIP.F._bestMatch(data, i, prev, c, Math.min(opt[2],dlen-i), opt[3]);
      /*
      if(mch!=0 && opt[4]==1 && (mch>>>16)<opt[1] && i+1<dlen-2) {
        nmch = UZIP.F._bestMatch(data, i+1, prev, nc, opt[2], opt[3]);  nmci=i+1;
        //var mch2 = UZIP.F._bestMatch(data, i+2, prev, nnc);  //nmci=i+1;
        if((nmch>>>16)>(mch>>>16)) mch=0;
      }//*/
      var len = mch>>>16, dst = mch&0xffff;  //if(i-dst<0) throw "e";
      if(mch!=0) { 
        var len = mch>>>16, dst = mch&0xffff;  //if(i-dst<0) throw "e";
        var lgi = goodIndex(len, U.of0);  U.lhst[257+lgi]++; 
        var dgi = goodIndex(dst, U.df0);  U.dhst[    dgi]++;  ebits += U.exb[lgi] + U.dxb[dgi]; 
        lits[li] = (len<<23)|(i-cvrd);  lits[li+1] = (dst<<16)|(lgi<<8)|dgi;  li+=2;
        cvrd = i + len;  
      }
      else {  U.lhst[data[i]]++;  }
      lc++;
    }
  }
  if(bs!=i || data.length==0) {
    if(cvrd<i) {  lits[li]=i-cvrd;  li+=2;  cvrd=i;  }
    pos = UZIP.F._writeBlock(1, lits, li, ebits, data,bs,i-bs, out, pos);  li=0;  lc=0;  li=lc=ebits=0;  bs=i;
  }
  while((pos&7)!=0) pos++;
  return pos>>>3;
}
UZIP.F._bestMatch = function(data, i, prev, c, nice, chain) {
  var ci = (i&0x7fff), pi=prev[ci];  
  //console.log("----", i);
  var dif = ((ci-pi + (1<<15)) & 0x7fff);  if(pi==ci || c!=UZIP.F._hash(data,i-dif)) return 0;
  var tl=0, td=0;  // top length, top distance
  var dlim = Math.min(0x7fff, i);
  while(dif<=dlim && --chain!=0 && pi!=ci /*&& c==UZIP.F._hash(data,i-dif)*/) {
    if(tl==0 || (data[i+tl]==data[i+tl-dif])) {
      var cl = UZIP.F._howLong(data, i, dif);
      if(cl>tl) {  
        tl=cl;  td=dif;  if(tl>=nice) break;    //* 
        if(dif+2<cl) cl = dif+2;
        var maxd = 0; // pi does not point to the start of the word
        for(var j=0; j<cl-2; j++) {
          var ei =  (i-dif+j+ (1<<15)) & 0x7fff;
          var li = prev[ei];
          var curd = (ei-li + (1<<15)) & 0x7fff;
          if(curd>maxd) {  maxd=curd;  pi = ei; }
        }  //*/
      }
    }
    
    ci=pi;  pi = prev[ci];
    dif += ((ci-pi + (1<<15)) & 0x7fff);
  }
  return (tl<<16)|td;
}
UZIP.F._howLong = function(data, i, dif) {
  if(data[i]!=data[i-dif] || data[i+1]!=data[i+1-dif] || data[i+2]!=data[i+2-dif]) return 0;
  var oi=i, l = Math.min(data.length, i+258);  i+=3;
  //while(i+4<l && data[i]==data[i-dif] && data[i+1]==data[i+1-dif] && data[i+2]==data[i+2-dif] && data[i+3]==data[i+3-dif]) i+=4;
  while(i<l && data[i]==data[i-dif]) i++;
  return i-oi;
}
UZIP.F._hash = function(data, i) {
  return (((data[i]<<8) | data[i+1])+(data[i+2]<<4))&0xffff;
  //var hash_shift = 0, hash_mask = 255;
  //var h = data[i+1] % 251;
  //h = (((h << 8) + data[i+2]) % 251);
  //h = (((h << 8) + data[i+2]) % 251);
  //h = ((h<<hash_shift) ^ (c) ) & hash_mask;
  //return h | (data[i]<<8);
  //return (data[i] | (data[i+1]<<8));
}
//UZIP.___toth = 0;
UZIP.saved = 0;
UZIP.F._writeBlock = function(BFINAL, lits, li, ebits, data,o0,l0, out, pos) {
  var U = UZIP.F.U, putsF = UZIP.F._putsF, putsE = UZIP.F._putsE;
  
  //*
  var T, ML, MD, MH, numl, numd, numh, lset, dset;  U.lhst[256]++;
  T = UZIP.F.getTrees(); ML=T[0]; MD=T[1]; MH=T[2]; numl=T[3]; numd=T[4]; numh=T[5]; lset=T[6]; dset=T[7];
  
  var cstSize = (((pos+3)&7)==0 ? 0 : 8-((pos+3)&7)) + 32 + (l0<<3);
  var fxdSize = ebits + UZIP.F.contSize(U.fltree, U.lhst) + UZIP.F.contSize(U.fdtree, U.dhst);
  var dynSize = ebits + UZIP.F.contSize(U.ltree , U.lhst) + UZIP.F.contSize(U.dtree , U.dhst);
  dynSize    += 14 + 3*numh + UZIP.F.contSize(U.itree, U.ihst) + (U.ihst[16]*2 + U.ihst[17]*3 + U.ihst[18]*7);
  
  for(var j=0; j<286; j++) U.lhst[j]=0;   for(var j=0; j<30; j++) U.dhst[j]=0;   for(var j=0; j<19; j++) U.ihst[j]=0;
  //*/
  var BTYPE = (cstSize<fxdSize && cstSize<dynSize) ? 0 : ( fxdSize<dynSize ? 1 : 2 );
  putsF(out, pos, BFINAL);  putsF(out, pos+1, BTYPE);  pos+=3;
  
  var opos = pos;
  if(BTYPE==0) {
    while((pos&7)!=0) pos++;
    pos = UZIP.F._copyExact(data, o0, l0, out, pos);
  }
  else {
    var ltree, dtree;
    if(BTYPE==1) {  ltree=U.fltree;  dtree=U.fdtree;  }
    if(BTYPE==2) {  
      UZIP.F.makeCodes(U.ltree, ML);  UZIP.F.revCodes(U.ltree, ML);
      UZIP.F.makeCodes(U.dtree, MD);  UZIP.F.revCodes(U.dtree, MD);
      UZIP.F.makeCodes(U.itree, MH);  UZIP.F.revCodes(U.itree, MH);
      
      ltree = U.ltree;  dtree = U.dtree;
      
      putsE(out, pos,numl-257);  pos+=5;  // 286
      putsE(out, pos,numd-  1);  pos+=5;  // 30
      putsE(out, pos,numh-  4);  pos+=4;  // 19
      
      for(var i=0; i<numh; i++) putsE(out, pos+i*3, U.itree[(U.ordr[i]<<1)+1]);   pos+=3* numh;
      pos = UZIP.F._codeTiny(lset, U.itree, out, pos);
      pos = UZIP.F._codeTiny(dset, U.itree, out, pos);
    }
    
    var off=o0;
    for(var si=0; si<li; si+=2) {
      var qb=lits[si], len=(qb>>>23), end = off+(qb&((1<<23)-1));
      while(off<end) pos = UZIP.F._writeLit(data[off++], ltree, out, pos);
      
      if(len!=0) {
        var qc = lits[si+1], dst=(qc>>16), lgi=(qc>>8)&255, dgi=(qc&255);
        pos = UZIP.F._writeLit(257+lgi, ltree, out, pos);
        putsE(out, pos, len-U.of0[lgi]);  pos+=U.exb[lgi];
        
        pos = UZIP.F._writeLit(dgi, dtree, out, pos);
        putsF(out, pos, dst-U.df0[dgi]);  pos+=U.dxb[dgi];  off+=len;
      }
    }
    pos = UZIP.F._writeLit(256, ltree, out, pos);
  }
  //console.log(pos-opos, fxdSize, dynSize, cstSize);
  return pos;
}
UZIP.F._copyExact = function(data,off,len,out,pos) {
  var p8 = (pos>>>3);
  out[p8]=(len);  out[p8+1]=(len>>>8);  out[p8+2]=255-out[p8];  out[p8+3]=255-out[p8+1];  p8+=4;
  out.set(new Uint8Array(data.buffer, off, len), p8);
  //for(var i=0; i<len; i++) out[p8+i]=data[off+i];
  return pos + ((len+4)<<3);
}
/*
  Interesting facts:
  - decompressed block can have bytes, which do not occur in a Huffman tree (copied from the previous block by reference)
*/

UZIP.F.getTrees = function() {
  var U = UZIP.F.U;
  var ML = UZIP.F._hufTree(U.lhst, U.ltree, 15);
  var MD = UZIP.F._hufTree(U.dhst, U.dtree, 15);
  var lset = [], numl = UZIP.F._lenCodes(U.ltree, lset);
  var dset = [], numd = UZIP.F._lenCodes(U.dtree, dset);
  for(var i=0; i<lset.length; i+=2) U.ihst[lset[i]]++;
  for(var i=0; i<dset.length; i+=2) U.ihst[dset[i]]++;
  var MH = UZIP.F._hufTree(U.ihst, U.itree,  7);
  var numh = 19;  while(numh>4 && U.itree[(U.ordr[numh-1]<<1)+1]==0) numh--;
  return [ML, MD, MH, numl, numd, numh, lset, dset];
}
UZIP.F.getSecond= function(a) {  var b=[];  for(var i=0; i<a.length; i+=2) b.push  (a[i+1]);  return b;  }
UZIP.F.nonZero  = function(a) {  var b= "";  for(var i=0; i<a.length; i+=2) if(a[i+1]!=0)b+=(i>>1)+",";  return b;  }
UZIP.F.contSize = function(tree, hst) {  var s=0;  for(var i=0; i<hst.length; i++) s+= hst[i]*tree[(i<<1)+1];  return s;  }
UZIP.F._codeTiny = function(set, tree, out, pos) {
  for(var i=0; i<set.length; i+=2) {
    var l = set[i], rst = set[i+1];  //console.log(l, pos, tree[(l<<1)+1]);
    pos = UZIP.F._writeLit(l, tree, out, pos);
    var rsl = l==16 ? 2 : (l==17 ? 3 : 7);
    if(l>15) {  UZIP.F._putsE(out, pos, rst, rsl);  pos+=rsl;  }
  }
  return pos;
}
UZIP.F._lenCodes = function(tree, set) {
  var len=tree.length;  while(len!=2 && tree[len-1]==0) len-=2;  // when no distances, keep one code with length 0
  for(var i=0; i<len; i+=2) {
    var l = tree[i+1], nxt = (i+3<len ? tree[i+3]:-1),  nnxt = (i+5<len ? tree[i+5]:-1),  prv = (i==0 ? -1 : tree[i-1]);
    if(l==0 && nxt==l && nnxt==l) {
      var lz = i+5;
      while(lz+2<len && tree[lz+2]==l) lz+=2;
      var zc = Math.min((lz+1-i)>>>1, 138);
      if(zc<11) set.push(17, zc-3);
      else set.push(18, zc-11);
      i += zc*2-2;
    }
    else if(l==prv && nxt==l && nnxt==l) {
      var lz = i+5;
      while(lz+2<len && tree[lz+2]==l) lz+=2;
      var zc = Math.min((lz+1-i)>>>1, 6);
      set.push(16, zc-3);
      i += zc*2-2;
    }
    else set.push(l, 0);
  }
  return len>>>1;
}
UZIP.F._hufTree   = function(hst, tree, MAXL) {
  var list=[], hl = hst.length, tl=tree.length, i=0;
  for(i=0; i<tl; i+=2) {  tree[i]=0;  tree[i+1]=0;  } 
  for(i=0; i<hl; i++) if(hst[i]!=0) list.push({lit:i, f:hst[i]});
  var end = list.length, l2=list.slice(0);
  if(end==0) return 0;  // empty histogram (usually for dist)
  if(end==1) {  var lit=list[0].lit, l2=lit==0?1:0;  tree[(lit<<1)+1]=1;  tree[(l2<<1)+1]=1;  return 1;  }
  list.sort(function(a,b){return a.f-b.f;});
  var a=list[0], b=list[1], i0=0, i1=1, i2=2;  list[0]={lit:-1,f:a.f+b.f,l:a,r:b,d:0};
  while(i1!=end-1) {
    if(i0!=i1 && (i2==end || list[i0].f<list[i2].f)) {  a=list[i0++];  }  else {  a=list[i2++];  }
    if(i0!=i1 && (i2==end || list[i0].f<list[i2].f)) {  b=list[i0++];  }  else {  b=list[i2++];  }
    list[i1++]={lit:-1,f:a.f+b.f, l:a,r:b};
  }
  var maxl = UZIP.F.setDepth(list[i1-1], 0);
  if(maxl>MAXL) {  UZIP.F.restrictDepth(l2, MAXL, maxl);  maxl = MAXL;  }
  for(i=0; i<end; i++) tree[(l2[i].lit<<1)+1]=l2[i].d;
  return maxl;
}

UZIP.F.setDepth  = function(t, d) {
  if(t.lit!=-1) {  t.d=d;  return d;  }
  return Math.max( UZIP.F.setDepth(t.l, d+1),  UZIP.F.setDepth(t.r, d+1) );
}

UZIP.F.restrictDepth = function(dps, MD, maxl) {
  var i=0, bCost=1<<(maxl-MD), dbt=0;
  dps.sort(function(a,b){return b.d==a.d ? a.f-b.f : b.d-a.d;});
  
  for(i=0; i<dps.length; i++) if(dps[i].d>MD) {  var od=dps[i].d;  dps[i].d=MD;  dbt+=bCost-(1<<(maxl-od));  }  else break;
  dbt = dbt>>>(maxl-MD);
  while(dbt>0) {  var od=dps[i].d;  if(od<MD) {  dps[i].d++;  dbt-=(1<<(MD-od-1));  }  else  i++;  }
  for(; i>=0; i--) if(dps[i].d==MD && dbt<0) {  dps[i].d--;  dbt++;  }  if(dbt!=0) console.log("debt left");
}

UZIP.F._goodIndex = function(v, arr) {
  var i=0;  if(arr[i|16]<=v) i|=16;  if(arr[i|8]<=v) i|=8;  if(arr[i|4]<=v) i|=4;  if(arr[i|2]<=v) i|=2;  if(arr[i|1]<=v) i|=1;  return i;
}
UZIP.F._writeLit = function(ch, ltree, out, pos) {
  UZIP.F._putsF(out, pos, ltree[ch<<1]);
  return pos+ltree[(ch<<1)+1];
}








UZIP.F.inflate = function(data, buf) {
  var u8=Uint8Array;
  if(data[0]==3 && data[1]==0) return (buf ? buf : new u8(0));
  var F=UZIP.F, bitsF = F._bitsF, bitsE = F._bitsE, decodeTiny = F._decodeTiny, makeCodes = F.makeCodes, codes2map=F.codes2map, get17 = F._get17;
  var U = F.U;
  
  var noBuf = (buf==null);
  if(noBuf) buf = new u8((data.length>>>2)<<3);
  
  var BFINAL=0, BTYPE=0, HLIT=0, HDIST=0, HCLEN=0, ML=0, MD=0;  
  var off = 0, pos = 0;
  var lmap, dmap;
  
  while(BFINAL==0) {    
    BFINAL = bitsF(data, pos  , 1);
    BTYPE  = bitsF(data, pos+1, 2);  pos+=3;
    //console.log(BFINAL, BTYPE);
    
    if(BTYPE==0) {
      if((pos&7)!=0) pos+=8-(pos&7);
      var p8 = (pos>>>3)+4, len = data[p8-4]|(data[p8-3]<<8);  //console.log(len);//bitsF(data, pos, 16), 
      if(noBuf) buf=UZIP.F._check(buf, off+len);
      buf.set(new u8(data.buffer, data.byteOffset+p8, len), off);
      //for(var i=0; i<len; i++) buf[off+i] = data[p8+i];
      //for(var i=0; i<len; i++) if(buf[off+i] != data[p8+i]) throw "e";
      pos = ((p8+len)<<3);  off+=len;  continue;
    }
    if(noBuf) buf=UZIP.F._check(buf, off+(1<<17));  // really not enough in many cases (but PNG and ZIP provide buffer in advance)
    if(BTYPE==1) {  lmap = U.flmap;  dmap = U.fdmap;  ML = (1<<9)-1;  MD = (1<<5)-1;   }
    if(BTYPE==2) {
      HLIT  = bitsE(data, pos   , 5)+257;  
      HDIST = bitsE(data, pos+ 5, 5)+  1;  
      HCLEN = bitsE(data, pos+10, 4)+  4;  pos+=14;
      
      var ppos = pos;
      for(var i=0; i<38; i+=2) {  U.itree[i]=0;  U.itree[i+1]=0;  }
      var tl = 1;
      for(var i=0; i<HCLEN; i++) {  var l=bitsE(data, pos+i*3, 3);  U.itree[(U.ordr[i]<<1)+1] = l;  if(l>tl)tl=l;  }     pos+=3*HCLEN;  //console.log(itree);
      makeCodes(U.itree, tl);
      codes2map(U.itree, tl, U.imap);
      
      lmap = U.lmap;  dmap = U.dmap;
      
      pos = decodeTiny(U.imap, (1<<tl)-1, HLIT+HDIST, data, pos, U.ttree);
      var mx0 = F._copyOut(U.ttree,    0, HLIT , U.ltree);  ML = (1<<mx0)-1;
      var mx1 = F._copyOut(U.ttree, HLIT, HDIST, U.dtree);  MD = (1<<mx1)-1;
      
      //var ml = decodeTiny(U.imap, (1<<tl)-1, HLIT , data, pos, U.ltree); ML = (1<<(ml>>>24))-1;  pos+=(ml&0xffffff);
      makeCodes(U.ltree, mx0);
      codes2map(U.ltree, mx0, lmap);
      
      //var md = decodeTiny(U.imap, (1<<tl)-1, HDIST, data, pos, U.dtree); MD = (1<<(md>>>24))-1;  pos+=(md&0xffffff);
      makeCodes(U.dtree, mx1);
      codes2map(U.dtree, mx1, dmap);
    }
    //var ooff=off, opos=pos;
    while(true) {
      var code = lmap[get17(data, pos) & ML];  pos += code&15;
      var lit = code>>>4;  //U.lhst[lit]++;  
      if((lit>>>8)==0) {  buf[off++] = lit;  }
      else if(lit==256) {  break;  }
      else {
        var end = off+lit-254;
        if(lit>264) { var ebs = U.ldef[lit-257];  end = off + (ebs>>>3) + bitsE(data, pos, ebs&7);  pos += ebs&7;  }
        //UZIP.F.dst[end-off]++;
        
        var dcode = dmap[get17(data, pos) & MD];  pos += dcode&15;
        var dlit = dcode>>>4;
        var dbs = U.ddef[dlit], dst = (dbs>>>4) + bitsF(data, pos, dbs&15);  pos += dbs&15;
        
        //var o0 = off-dst, stp = Math.min(end-off, dst);
        //if(stp>20) while(off<end) {  buf.copyWithin(off, o0, o0+stp);  off+=stp;  }  else
        //if(end-dst<=off) buf.copyWithin(off, off-dst, end-dst);  else
        //if(dst==1) buf.fill(buf[off-1], off, end);  else
        if(noBuf) buf=UZIP.F._check(buf, off+(1<<17));
        while(off<end) {  buf[off]=buf[off++-dst];    buf[off]=buf[off++-dst];  buf[off]=buf[off++-dst];  buf[off]=buf[off++-dst];  }   
        off=end;
        //while(off!=end) {  buf[off]=buf[off++-dst];  }
      }
    }
    //console.log(off-ooff, (pos-opos)>>>3);
  }
  //console.log(UZIP.F.dst);
  //console.log(tlen, dlen, off-tlen+tcnt);
  return buf.length==off ? buf : buf.slice(0,off);
}
UZIP.F._check=function(buf, len) {
  var bl=buf.length;  if(len<=bl) return buf;
  var nbuf = new Uint8Array(Math.max(bl<<1,len));  nbuf.set(buf,0);
  //for(var i=0; i<bl; i+=4) {  nbuf[i]=buf[i];  nbuf[i+1]=buf[i+1];  nbuf[i+2]=buf[i+2];  nbuf[i+3]=buf[i+3];  }
  return nbuf;
}

UZIP.F._decodeTiny = function(lmap, LL, len, data, pos, tree) {
  var bitsE = UZIP.F._bitsE, get17 = UZIP.F._get17;
  var i = 0;
  while(i<len) {
    var code = lmap[get17(data, pos)&LL];  pos+=code&15;
    var lit = code>>>4; 
    if(lit<=15) {  tree[i]=lit;  i++;  }
    else {
      var ll = 0, n = 0;
      if(lit==16) {
        n = (3  + bitsE(data, pos, 2));  pos += 2;  ll = tree[i-1];
      }
      else if(lit==17) {
        n = (3  + bitsE(data, pos, 3));  pos += 3;
      }
      else if(lit==18) {
        n = (11 + bitsE(data, pos, 7));  pos += 7;
      }
      var ni = i+n;
      while(i<ni) {  tree[i]=ll;  i++; }
    }
  }
  return pos;
}
UZIP.F._copyOut = function(src, off, len, tree) {
  var mx=0, i=0, tl=tree.length>>>1;
  while(i<len) {  var v=src[i+off];  tree[(i<<1)]=0;  tree[(i<<1)+1]=v;  if(v>mx)mx=v;  i++;  }
  while(i<tl ) {  tree[(i<<1)]=0;  tree[(i<<1)+1]=0;  i++;  }
  return mx;
}

UZIP.F.makeCodes = function(tree, MAX_BITS) {  // code, length
  var U = UZIP.F.U;
  var max_code = tree.length;
  var code, bits, n, i, len;
  
  var bl_count = U.bl_count;  for(var i=0; i<=MAX_BITS; i++) bl_count[i]=0;
  for(i=1; i<max_code; i+=2) bl_count[tree[i]]++;
  
  var next_code = U.next_code;  // smallest code for each length
  
  code = 0;
  bl_count[0] = 0;
  for (bits = 1; bits <= MAX_BITS; bits++) {
    code = (code + bl_count[bits-1]) << 1;
    next_code[bits] = code;
  }
  
  for (n = 0; n < max_code; n+=2) {
    len = tree[n+1];
    if (len != 0) {
      tree[n] = next_code[len];
      next_code[len]++;
    }
  }
}
UZIP.F.codes2map = function(tree, MAX_BITS, map) {
  var max_code = tree.length;
  var U=UZIP.F.U, r15 = U.rev15;
  for(var i=0; i<max_code; i+=2) if(tree[i+1]!=0)  {
    var lit = i>>1;
    var cl = tree[i+1], val = (lit<<4)|cl; // :  (0x8000 | (U.of0[lit-257]<<7) | (U.exb[lit-257]<<4) | cl);
    var rest = (MAX_BITS-cl), i0 = tree[i]<<rest, i1 = i0 + (1<<rest);
    //tree[i]=r15[i0]>>>(15-MAX_BITS);
    while(i0!=i1) {
      var p0 = r15[i0]>>>(15-MAX_BITS);
      map[p0]=val;  i0++;
    }
  }
}
UZIP.F.revCodes = function(tree, MAX_BITS) {
  var r15 = UZIP.F.U.rev15, imb = 15-MAX_BITS;
  for(var i=0; i<tree.length; i+=2) {  var i0 = (tree[i]<<(MAX_BITS-tree[i+1]));  tree[i] = r15[i0]>>>imb;  }
}

// used only in deflate
UZIP.F._putsE= function(dt, pos, val   ) {  val = val<<(pos&7);  var o=(pos>>>3);  dt[o]|=val;  dt[o+1]|=(val>>>8);                        }
UZIP.F._putsF= function(dt, pos, val   ) {  val = val<<(pos&7);  var o=(pos>>>3);  dt[o]|=val;  dt[o+1]|=(val>>>8);  dt[o+2]|=(val>>>16);  }

UZIP.F._bitsE= function(dt, pos, length) {  return ((dt[pos>>>3] | (dt[(pos>>>3)+1]<<8)                        )>>>(pos&7))&((1<<length)-1);  }
UZIP.F._bitsF= function(dt, pos, length) {  return ((dt[pos>>>3] | (dt[(pos>>>3)+1]<<8) | (dt[(pos>>>3)+2]<<16))>>>(pos&7))&((1<<length)-1);  }
/*
UZIP.F._get9 = function(dt, pos) {
  return ((dt[pos>>>3] | (dt[(pos>>>3)+1]<<8))>>>(pos&7))&511;
} */
UZIP.F._get17= function(dt, pos) {  // return at least 17 meaningful bytes
  return (dt[pos>>>3] | (dt[(pos>>>3)+1]<<8) | (dt[(pos>>>3)+2]<<16) )>>>(pos&7);
}
UZIP.F._get25= function(dt, pos) {  // return at least 17 meaningful bytes
  return (dt[pos>>>3] | (dt[(pos>>>3)+1]<<8) | (dt[(pos>>>3)+2]<<16) | (dt[(pos>>>3)+3]<<24) )>>>(pos&7);
}
UZIP.F.U = function(){
  var u16=Uint16Array, u32=Uint32Array;
  return {
    next_code : new u16(16),
    bl_count  : new u16(16),
    ordr : [ 16, 17, 18, 0, 8, 7, 9, 6, 10, 5, 11, 4, 12, 3, 13, 2, 14, 1, 15 ],
    of0  : [3,4,5,6,7,8,9,10,11,13,15,17,19,23,27,31,35,43,51,59,67,83,99,115,131,163,195,227,258,999,999,999],
    exb  : [0,0,0,0,0,0,0, 0, 1, 1, 1, 1, 2, 2, 2, 2, 3, 3, 3, 3, 4, 4, 4,  4,  5,  5,  5,  5,  0,  0,  0,  0],
    ldef : new u16(32),
    df0  : [1,2,3,4,5,7,9,13,17,25,33,49,65,97,129,193,257,385,513,769,1025,1537,2049,3073,4097,6145,8193,12289,16385,24577, 65535, 65535],
    dxb  : [0,0,0,0,1,1,2, 2, 3, 3, 4, 4, 5, 5,  6,  6,  7,  7,  8,  8,   9,   9,  10,  10,  11,  11,  12,   12,   13,   13,     0,     0],
    ddef : new u32(32),
    flmap: new u16(  512),  fltree: [],
    fdmap: new u16(   32),  fdtree: [],
    lmap : new u16(32768),  ltree : [],  ttree:[],
    dmap : new u16(32768),  dtree : [],
    imap : new u16(  512),  itree : [],
    //rev9 : new u16(  512)
    rev15: new u16(1<<15),
    lhst : new u32(286), dhst : new u32( 30), ihst : new u32(19),
    lits : new u32(15000),
    strt : new u16(1<<16),
    prev : new u16(1<<15)
  };  
} ();

(function(){  
  var U = UZIP.F.U;
  var len = 1<<15;
  for(var i=0; i<len; i++) {
    var x = i;
    x = (((x & 0xaaaaaaaa) >>> 1) | ((x & 0x55555555) << 1));
    x = (((x & 0xcccccccc) >>> 2) | ((x & 0x33333333) << 2));
    x = (((x & 0xf0f0f0f0) >>> 4) | ((x & 0x0f0f0f0f) << 4));
    x = (((x & 0xff00ff00) >>> 8) | ((x & 0x00ff00ff) << 8));
    U.rev15[i] = (((x >>> 16) | (x << 16)))>>>17;
  }
  
  function pushV(tgt, n, sv) {  while(n--!=0) tgt.push(0,sv);  }
  
  for(var i=0; i<32; i++) {  U.ldef[i]=(U.of0[i]<<3)|U.exb[i];  U.ddef[i]=(U.df0[i]<<4)|U.dxb[i];  }
  
  pushV(U.fltree, 144, 8);  pushV(U.fltree, 255-143, 9);  pushV(U.fltree, 279-255, 7);  pushV(U.fltree,287-279,8);
  /*
  var i = 0;
  for(; i<=143; i++) U.fltree.push(0,8);
  for(; i<=255; i++) U.fltree.push(0,9);
  for(; i<=279; i++) U.fltree.push(0,7);
  for(; i<=287; i++) U.fltree.push(0,8);
  */
  UZIP.F.makeCodes(U.fltree, 9);
  UZIP.F.codes2map(U.fltree, 9, U.flmap);
  UZIP.F.revCodes (U.fltree, 9)
  
  pushV(U.fdtree,32,5);
  //for(i=0;i<32; i++) U.fdtree.push(0,5);
  UZIP.F.makeCodes(U.fdtree, 5);
  UZIP.F.codes2map(U.fdtree, 5, U.fdmap);
  UZIP.F.revCodes (U.fdtree, 5)
  
  pushV(U.itree,19,0);  pushV(U.ltree,286,0);  pushV(U.dtree,30,0);  pushV(U.ttree,320,0);
  /*
  for(var i=0; i< 19; i++) U.itree.push(0,0);
  for(var i=0; i<286; i++) U.ltree.push(0,0);
  for(var i=0; i< 30; i++) U.dtree.push(0,0);
  for(var i=0; i<320; i++) U.ttree.push(0,0);
  */
})()
















