/**
 * Tasty Fonts Font Binary Parser
 *
 * Self-contained vanilla JS font metadata parser.
 * Supports TTF, OTF, WOFF (uncompressed and deflate-compressed tables).
 * WOFF2 uses filename fallback only (no directory scanning).
 */
(function (global) {
  'use strict';

  // ---------------------------------------------------------------------------
  // Local normalization fallbacks (so parser works standalone)
  // ---------------------------------------------------------------------------

  function localNormalizeAxisTag(tag) {
    const str = String(tag).toUpperCase().trim();
    if (/^[A-Z0-9]{4}$/.test(str)) {
      return str;
    }
    return null;
  }

  function localNormalizeAxisValue(value) {
    const num = Number(value);
    if (Number.isNaN(num)) {
      return null;
    }
    return String(Math.round(num));
  }

  function getNormalizeAxisTag() {
    if (
      typeof window !== 'undefined' &&
      window.TastyFontsAdminContracts &&
      typeof window.TastyFontsAdminContracts.normalizeAxisTag === 'function'
    ) {
      return window.TastyFontsAdminContracts.normalizeAxisTag;
    }
    return localNormalizeAxisTag;
  }

  function getNormalizeAxisValue() {
    if (
      typeof window !== 'undefined' &&
      window.TastyFontsAdminContracts &&
      typeof window.TastyFontsAdminContracts.normalizeAxisValue === 'function'
    ) {
      return window.TastyFontsAdminContracts.normalizeAxisValue;
    }
    return localNormalizeAxisValue;
  }

  // ---------------------------------------------------------------------------
  // Binary helpers
  // ---------------------------------------------------------------------------

  function decodeUtf16BE(bytes) {
    const chars = [];
    for (let i = 0; i < bytes.length - 1; i += 2) {
      const code = (bytes[i] << 8) | bytes[i + 1];
      chars.push(String.fromCharCode(code));
    }
    return chars.join('');
  }

  function decodeMacRoman(bytes) {
    // MacRoman is ASCII-compatible for typical family names.
    const chars = [];
    for (let i = 0; i < bytes.length; i++) {
      chars.push(String.fromCharCode(bytes[i]));
    }
    return chars.join('');
  }

  // ---------------------------------------------------------------------------
  // Format detection
  // ---------------------------------------------------------------------------

  function detectFormat(buffer) {
    if (buffer.byteLength < 4) {
      return null;
    }
    const view = new DataView(buffer);
    const b0 = view.getUint8(0);
    const b1 = view.getUint8(1);
    const b2 = view.getUint8(2);
    const b3 = view.getUint8(3);

    // TTF / TrueType: 0x00010000
    if (b0 === 0x00 && b1 === 0x01 && b2 === 0x00 && b3 === 0x00) {
      return 'ttf';
    }

    // Legacy TrueType signatures: 'true', 'typ1'
    const tag = String.fromCharCode(b0, b1, b2, b3);
    if (tag === 'true' || tag === 'typ1') {
      return 'ttf';
    }

    // OpenType CFF: 'OTTO'
    if (b0 === 0x4F && b1 === 0x54 && b2 === 0x54 && b3 === 0x4F) {
      return 'otf';
    }

    // WOFF: 'wOFF'
    if (b0 === 0x77 && b1 === 0x4F && b2 === 0x46 && b3 === 0x46) {
      return 'woff';
    }

    // WOFF2: 'wOF2'
    if (b0 === 0x77 && b1 === 0x4F && b2 === 0x46 && b3 === 0x32) {
      return 'woff2';
    }

    return null;
  }

  // ---------------------------------------------------------------------------
  // SFNT table directory parsing
  // ---------------------------------------------------------------------------

  function readSfntTag(view, offset) {
    return String.fromCharCode(
      view.getUint8(offset),
      view.getUint8(offset + 1),
      view.getUint8(offset + 2),
      view.getUint8(offset + 3)
    );
  }

  function findSfntTables(sourceBuffer) {
    if (sourceBuffer.byteLength < 6) {
      return {};
    }
    const view = new DataView(sourceBuffer);
    const numTables = view.getUint16(4, false);
    const tables = {};

    for (let i = 0; i < numTables; i++) {
      const recOffset = 12 + i * 16;
      if (recOffset + 16 > sourceBuffer.byteLength) {
        continue;
      }
      const tag = readSfntTag(view, recOffset);
      const offset = view.getUint32(recOffset + 8, false);
      const length = view.getUint32(recOffset + 12, false);
      if (offset > sourceBuffer.byteLength || offset + length > sourceBuffer.byteLength) {
        continue;
      }
      tables[tag] = { buffer: sourceBuffer, offset, length };
    }

    return tables;
  }

  // ---------------------------------------------------------------------------
  // WOFF table directory parsing
  // ---------------------------------------------------------------------------

  async function decompressDeflate(sourceBuffer, offset, length, originalLength) {
    if (typeof DecompressionStream === 'undefined') {
      return null;
    }
    try {
      const input = new Uint8Array(sourceBuffer, offset, length);
      const ds = new DecompressionStream('deflate');
      const writer = ds.writable.getWriter();
      await writer.write(input);
      await writer.close();

      const reader = ds.readable.getReader();
      const chunks = [];
      let total = 0;
      while (true) {
        const { done, value } = await reader.read();
        if (done) {
          break;
        }
        chunks.push(value);
        total += value.length;
      }

      const output = new Uint8Array(total);
      let pos = 0;
      for (const chunk of chunks) {
        output.set(chunk, pos);
        pos += chunk.length;
      }

      if (output.byteLength !== originalLength) {
        return null;
      }

      return output.buffer.slice(output.byteOffset, output.byteOffset + output.byteLength);
    } catch (e) {
      return null;
    }
  }

  async function findWoffTables(sourceBuffer) {
    if (sourceBuffer.byteLength < 14) {
      return { tables: {}, hasFvar: false };
    }
    const view = new DataView(sourceBuffer);
    const numTables = view.getUint16(12, false);
    const tables = {};
    const needed = new Set(['name', 'OS/2', 'head', 'fvar']);
    let hasFvar = false;

    for (let i = 0; i < numTables; i++) {
      const recOffset = 44 + i * 20;
      if (recOffset + 20 > sourceBuffer.byteLength) {
        continue;
      }
      const tag = readSfntTag(view, recOffset);
      if (tag === 'fvar') {
        hasFvar = true;
      }
      if (!needed.has(tag)) {
        continue;
      }
      const offset = view.getUint32(recOffset + 4, false);
      const compLength = view.getUint32(recOffset + 8, false);
      const origLength = view.getUint32(recOffset + 12, false);

      if (offset > sourceBuffer.byteLength || offset + compLength > sourceBuffer.byteLength) {
        continue;
      }

      if (compLength === origLength) {
        tables[tag] = { buffer: sourceBuffer, offset, length: compLength };
      } else {
        const decompressed = await decompressDeflate(sourceBuffer, offset, compLength, origLength);
        if (decompressed) {
          tables[tag] = { buffer: decompressed, offset: 0, length: decompressed.byteLength };
        }
      }
    }

    return { tables, hasFvar };
  }

  // ---------------------------------------------------------------------------
  // WOFF2 directory scanning (no Brotli)
  // ---------------------------------------------------------------------------

  function detectWoff2Fvar(buffer) {
    // WOFF2 files use filename fallback for metadata detection.
    // Proper WOFF2 parsing requires Brotli decompression and full directory
    // decoding, which is not implemented here.
    return false;
  }

  // ---------------------------------------------------------------------------
  // Table parsers
  // ---------------------------------------------------------------------------

  function parseNameTable(tableInfo) {
    const buffer = tableInfo.buffer;
    const tableOffset = tableInfo.offset;
    const tableLength = tableInfo.length;

    if (tableLength < 6) {
      return null;
    }
    const view = new DataView(buffer, tableOffset, tableLength);
    const count = view.getUint16(2, false);
    const stringOffset = view.getUint16(4, false);

    if (tableLength < 6 + count * 12) {
      return null;
    }

    let bestRecord = null;
    let bestPriority = Infinity;

    for (let i = 0; i < count; i++) {
      const recOffset = 6 + i * 12;
      const platformID = view.getUint16(recOffset, false);
      const encodingID = view.getUint16(recOffset + 2, false);
      const languageID = view.getUint16(recOffset + 4, false);
      const nameID = view.getUint16(recOffset + 6, false);
      const length = view.getUint16(recOffset + 8, false);
      const offset = view.getUint16(recOffset + 10, false);

      if (nameID !== 1) {
        continue;
      }

      if (stringOffset + offset + length > tableLength) {
        continue;
      }
      const absOffset = tableOffset + stringOffset + offset;

      let priority;
      if (platformID === 3 && languageID === 0x0409) {
        priority = 1;
      } else if (platformID === 3) {
        priority = 2;
      } else if (platformID === 1 && encodingID === 0 && languageID === 0) {
        priority = 3;
      } else {
        priority = 4;
      }

      if (priority < bestPriority) {
        bestPriority = priority;
        bestRecord = { platformID, offset: absOffset, length };
      }
    }

    if (!bestRecord) {
      return null;
    }

    const bytes = new Uint8Array(buffer, bestRecord.offset, bestRecord.length);
    if (bestRecord.platformID === 3 || bestRecord.platformID === 0) {
      return decodeUtf16BE(bytes);
    }
    return decodeMacRoman(bytes);
  }

  function parseOs2Table(tableInfo) {
    const buffer = tableInfo.buffer;
    const tableOffset = tableInfo.offset;
    const tableLength = tableInfo.length;

    if (tableLength < 64) {
      return { weight: null, italic: false };
    }
    const view = new DataView(buffer, tableOffset, tableLength);
    const weightClass = view.getUint16(4, false);
    const fsSelection = view.getUint16(62, false);
    return {
      weight: String(weightClass),
      italic: (fsSelection & 0x0001) !== 0,
    };
  }

  function parseHeadTable(tableInfo) {
    const buffer = tableInfo.buffer;
    const tableOffset = tableInfo.offset;
    const tableLength = tableInfo.length;

    if (tableLength < 46) {
      return { oblique: false };
    }
    const view = new DataView(buffer, tableOffset, tableLength);
    const macStyle = view.getUint16(44, false);
    return {
      oblique: (macStyle & 0x0002) !== 0,
    };
  }

  function parseFvarTable(tableInfo) {
    const buffer = tableInfo.buffer;
    const tableOffset = tableInfo.offset;
    const tableLength = tableInfo.length;

    if (tableLength < 16) {
      return { axes: [] };
    }
    const view = new DataView(buffer, tableOffset, tableLength);
    const axesArrayOffset = view.getUint16(4, false);
    const axisCount = view.getUint16(8, false);
    const axisSize = view.getUint16(10, false);

    if (axisSize < 20 || tableLength < axesArrayOffset + axisCount * axisSize) {
      return { axes: [] };
    }

    const normalizeTag = getNormalizeAxisTag();
    const normalizeValue = getNormalizeAxisValue();
    const axes = [];

    for (let i = 0; i < axisCount; i++) {
      const recOff = axesArrayOffset + i * axisSize;
      const tag = String.fromCharCode(
        view.getUint8(recOff),
        view.getUint8(recOff + 1),
        view.getUint8(recOff + 2),
        view.getUint8(recOff + 3)
      );
      const minRaw = view.getInt32(recOff + 4, false);
      const defaultRaw = view.getInt32(recOff + 8, false);
      const maxRaw = view.getInt32(recOff + 12, false);

      const min = normalizeValue(minRaw / 65536);
      const def = normalizeValue(defaultRaw / 65536);
      const max = normalizeValue(maxRaw / 65536);
      const normalizedTag = normalizeTag(tag);

      if (!normalizedTag || min === null || def === null || max === null) {
        continue;
      }

      const minNum = Number(min);
      const defNum = Number(def);
      const maxNum = Number(max);
      if (minNum > maxNum || defNum < minNum || defNum > maxNum) {
        continue;
      }

      axes.push({
        tag: normalizedTag,
        min,
        default: def,
        max,
      });
    }

    return { axes };
  }

  // ---------------------------------------------------------------------------
  // Normalization helpers
  // ---------------------------------------------------------------------------

  function normalizeWeight(raw) {
    const num = parseInt(raw, 10);
    if (Number.isNaN(num)) {
      return '400';
    }
    const rounded = Math.round(num / 100) * 100;
    return String(Math.max(100, Math.min(900, rounded)));
  }

  function sanitizeFamilyName(name) {
    if (!name) {
      return null;
    }
    const sanitized = String(name)
      .replace(/[\x00-\x1F\x7F]/g, '')
      .replace(/\s+/g, ' ')
      .trim()
      .replace(/\*+$/, '')
      .trim()
      .slice(0, 100);
    return sanitized !== '' ? sanitized : null;
  }

  // ---------------------------------------------------------------------------
  // Core parser
  // ---------------------------------------------------------------------------

  async function parseBuffer(arrayBuffer, options) {
    let buffer = arrayBuffer;
    if (buffer instanceof Uint8Array) {
      buffer = buffer.buffer.slice(buffer.byteOffset, buffer.byteOffset + buffer.byteLength);
    }
    if (
      !(buffer instanceof ArrayBuffer) ||
      buffer.byteLength < 4
    ) {
      return null;
    }

    const format = detectFormat(buffer);
    if (!format) {
      return null;
    }

    // WOFF2 partial support
    if (format === 'woff2') {
      if (detectWoff2Fvar(buffer)) {
        return {
          family: null,
          weight: null,
          style: 'normal',
          isVariable: true,
          variableKnown: false,
          axes: [],
          source: 'woff2-directory',
          warnings: ['WOFF2 table data is compressed; only variable font presence detected from directory'],
        };
      }
      return null;
    }

    let tables = {};
    let woffHasFvar = false;
    try {
      if (format === 'ttf' || format === 'otf') {
        tables = findSfntTables(buffer);
      } else if (format === 'woff') {
        const woffResult = await findWoffTables(buffer);
        tables = woffResult.tables;
        woffHasFvar = woffResult.hasFvar;
      }
    } catch (e) {
      return null;
    }

    const result = {
      family: null,
      weight: null,
      style: null,
      isVariable: false,
      variableKnown: true,
      axes: [],
      source: 'binary',
      warnings: [],
    };

    try {
      if (tables.name) {
        const family = parseNameTable(tables.name);
        if (family) {
          result.family = sanitizeFamilyName(family);
        }
      }

      if (tables['OS/2']) {
        const os2 = parseOs2Table(tables['OS/2']);
        if (os2.weight !== null) {
          result.weight = normalizeWeight(os2.weight);
        }
        if (os2.italic) {
          result.style = 'italic';
        }
      }

      if (tables.head && result.style !== 'italic') {
        const head = parseHeadTable(tables.head);
        if (head.oblique) {
          result.style = 'oblique';
        }
      }

      if (tables['OS/2'] || tables.head) {
        result.style = result.style || 'normal';
      }

      if (tables.fvar) {
        result.isVariable = true;
        const fvar = parseFvarTable(tables.fvar);
        if (fvar.axes.length > 0) {
          result.axes = fvar.axes;
        }
      } else if (woffHasFvar) {
        result.isVariable = true;
      }
    } catch (e) {
      // Return whatever partial result we have so far.
    }

    // Weight and style remain null when not detected so callers can merge
    // with filename fallback before applying defaults.
    if (result.weight === null && result.style === null && !result.family
        && !result.isVariable && result.axes.length === 0) {
      return null;
    }

    if (result.isVariable && result.axes.length > 0) {
      const wghtAxis = result.axes.find(function (ax) {
        return ax.tag === 'WGHT';
      });
      if (wghtAxis) {
        result.weight = wghtAxis.min + '..' + wghtAxis.max;
      }
    }

    return result;
  }

  async function parse(file, options) {
    if (!file) {
      return null;
    }

    let buffer;
    try {
      if (typeof FileReader !== 'undefined') {
        buffer = await new Promise((resolve, reject) => {
          const reader = new FileReader();
          reader.onload = () => resolve(reader.result);
          reader.onerror = () => reject(reader.error);
          reader.onabort = () => reject(new DOMException('FileReader aborted', 'AbortError'));
          reader.readAsArrayBuffer(file);
        });
      } else if (typeof file.arrayBuffer === 'function') {
        buffer = await file.arrayBuffer();
      } else {
        return null;
      }
    } catch (e) {
      return null;
    }

    return parseBuffer(buffer, options);
  }

  // ---------------------------------------------------------------------------
  // Exports
  // ---------------------------------------------------------------------------

  const parser = {
    parse,
    parseBuffer,
  };

  global.TastyFontsFontBinaryParser = parser;

  if (typeof module === 'object' && module !== null && typeof module.exports !== 'undefined') {
    module.exports = parser;
  }
})(typeof globalThis !== 'undefined' ? globalThis : typeof window !== 'undefined' ? window : typeof global !== 'undefined' ? global : this);
