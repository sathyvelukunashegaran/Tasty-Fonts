const { describe, it } = require('node:test');
const assert = require('node:assert');
const parser = require('../../assets/js/font-binary-parser.js');

const { parseBuffer } = parser;

// ---------------------------------------------------------------------------
// Test helpers for building synthetic binary buffers
// ---------------------------------------------------------------------------

function utf16be(str) {
  const bytes = [];
  for (let i = 0; i < str.length; i++) {
    const code = str.charCodeAt(i);
    bytes.push((code >> 8) & 0xFF);
    bytes.push(code & 0xFF);
  }
  return Buffer.from(bytes);
}

function buildNameTable(familyName) {
  const nameBytes = utf16be(familyName);
  const recordSize = 12;
  const headerSize = 6;
  const stringOffset = headerSize + recordSize;
  const totalSize = stringOffset + nameBytes.length;

  const buf = Buffer.alloc(totalSize);
  const view = new DataView(buf.buffer, buf.byteOffset, buf.byteLength);

  view.setUint16(0, 0, false); // format
  view.setUint16(2, 1, false); // count
  view.setUint16(4, stringOffset, false); // stringOffset

  // Record: Windows Unicode English, nameID 1
  view.setUint16(6, 3, false); // platformID
  view.setUint16(8, 1, false); // encodingID
  view.setUint16(10, 0x0409, false); // languageID
  view.setUint16(12, 1, false); // nameID
  view.setUint16(14, nameBytes.length, false); // length
  view.setUint16(16, 0, false); // offset

  nameBytes.copy(buf, stringOffset);
  return buf;
}

function buildSfnt(tables) {
  const numTables = tables.length;
  const headerSize = 12;
  const recordSize = 16;

  let tableOffset = headerSize + numTables * recordSize;

  const records = [];
  for (const table of tables) {
    records.push({
      tag: table.tag,
      offset: tableOffset,
      length: table.data.length,
    });
    tableOffset += table.data.length;
  }

  const totalSize = tableOffset;
  const buf = Buffer.alloc(totalSize);
  const view = new DataView(buf.buffer, buf.byteOffset, buf.byteLength);

  view.setUint32(0, 0x00010000, false); // sfntVersion (TrueType)
  view.setUint16(4, numTables, false);
  view.setUint16(6, 16, false); // searchRange
  view.setUint16(8, 0, false); // entrySelector
  view.setUint16(10, 0, false); // rangeShift

  for (let i = 0; i < numTables; i++) {
    const rec = records[i];
    const off = headerSize + i * recordSize;
    for (let j = 0; j < 4; j++) {
      buf[off + j] = rec.tag.charCodeAt(j);
    }
    view.setUint32(off + 4, 0, false); // checksum
    view.setUint32(off + 8, rec.offset, false);
    view.setUint32(off + 12, rec.length, false);
    tables[i].data.copy(buf, rec.offset);
  }

  return buf;
}

function buildOs2Table(weightClass, fsSelection) {
  const buf = Buffer.alloc(78);
  const view = new DataView(buf.buffer, buf.byteOffset, buf.byteLength);
  view.setUint16(0, 4, false); // version
  view.setUint16(2, 500, false); // xAvgCharWidth
  view.setUint16(4, weightClass, false); // usWeightClass
  view.setUint16(62, fsSelection, false); // fsSelection
  return buf;
}

function buildHeadTable(macStyle) {
  const buf = Buffer.alloc(54);
  const view = new DataView(buf.buffer, buf.byteOffset, buf.byteLength);
  view.setUint32(0, 0x00010000, false); // version
  view.setUint32(12, 0x5F0F3CF5, false); // magicNumber
  view.setUint16(44, macStyle, false); // macStyle
  return buf;
}

function buildFvarTable(axes) {
  const headerSize = 16;
  const axisSize = 20;
  const totalSize = headerSize + axes.length * axisSize;

  const buf = Buffer.alloc(totalSize);
  const view = new DataView(buf.buffer, buf.byteOffset, buf.byteLength);

  view.setUint16(0, 1, false); // majorVersion
  view.setUint16(2, 0, false); // minorVersion
  view.setUint16(4, headerSize, false); // axesArrayOffset
  view.setUint16(6, 0, false); // reserved
  view.setUint16(8, axes.length, false); // axisCount
  view.setUint16(10, axisSize, false); // axisSize
  view.setUint16(12, 0, false); // instanceCount
  view.setUint16(14, 0, false); // instanceSize

  for (let i = 0; i < axes.length; i++) {
    const ax = axes[i];
    const off = headerSize + i * axisSize;
    for (let j = 0; j < 4; j++) {
      buf[off + j] = ax.tag.charCodeAt(j);
    }
    view.setInt32(off + 4, Math.round(ax.min * 65536), false);
    view.setInt32(off + 8, Math.round(ax.default * 65536), false);
    view.setInt32(off + 12, Math.round(ax.max * 65536), false);
    view.setUint16(off + 16, 0, false); // flags
    view.setUint16(off + 18, 256, false); // axisNameID
  }

  return buf;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('font-binary-parser', () => {
  it('extracts family name from TTF/SFNT name table', async () => {
    const nameTable = buildNameTable('TestFamily');
    const sfnt = buildSfnt([{ tag: 'name', data: nameTable }]);
    const result = await parseBuffer(sfnt);
    assert.strictEqual(result.family, 'TestFamily');
    assert.strictEqual(result.variableKnown, true);
  });

  it('extracts weight from OS/2.usWeightClass', async () => {
    const os2 = buildOs2Table(700, 0);
    const sfnt = buildSfnt([{ tag: 'OS/2', data: os2 }]);
    const result = await parseBuffer(sfnt);
    assert.strictEqual(result.weight, '700');
    assert.strictEqual(result.style, 'normal');
    assert.strictEqual(result.variableKnown, true);
  });

  it('detects italic from OS/2.fsSelection bit 0', async () => {
    const os2 = buildOs2Table(400, 0x0001);
    const sfnt = buildSfnt([{ tag: 'OS/2', data: os2 }]);
    const result = await parseBuffer(sfnt);
    assert.strictEqual(result.style, 'italic');
    assert.strictEqual(result.variableKnown, true);
  });

  it('detects oblique from head.macStyle bit 1', async () => {
    const os2 = buildOs2Table(400, 0x0000);
    const head = buildHeadTable(0x0002);
    const sfnt = buildSfnt([
      { tag: 'OS/2', data: os2 },
      { tag: 'head', data: head },
    ]);
    const result = await parseBuffer(sfnt);
    assert.strictEqual(result.style, 'oblique');
    assert.strictEqual(result.variableKnown, true);
  });

  it('extracts fvar axes and marks variable', async () => {
    const fvar = buildFvarTable([
      { tag: 'WGHT', min: 100, default: 400, max: 900 },
    ]);
    const sfnt = buildSfnt([{ tag: 'fvar', data: fvar }]);
    const result = await parseBuffer(sfnt);
    assert.strictEqual(result.isVariable, true);
    assert.strictEqual(result.variableKnown, true);
    assert.strictEqual(result.weight, '100..900');
    assert.deepStrictEqual(result.axes, [
      { tag: 'WGHT', min: '100', default: '400', max: '900' },
    ]);
  });

  it('does not throw on malformed offsets', async () => {
    const buf = Buffer.alloc(28); // header + one record only
    const view = new DataView(buf.buffer, buf.byteOffset, buf.byteLength);
    view.setUint32(0, 0x00010000, false);
    view.setUint16(4, 1, false);
    for (let i = 0; i < 4; i++) {
      buf[12 + i] = 'name'.charCodeAt(i);
    }
    view.setUint32(12 + 8, 1000, false); // offset beyond buffer
    view.setUint32(12 + 12, 100, false); // length

    let threw = false;
    let result;
    try {
      result = await parseBuffer(buf);
    } catch (e) {
      threw = true;
    }
    assert.strictEqual(threw, false);
    assert.strictEqual(result, null);
  });

  it('returns null fields for partial binary result so fallback can merge', async () => {
    const nameTable = buildNameTable('PartialFamily');
    const sfnt = buildSfnt([{ tag: 'name', data: nameTable }]);
    const result = await parseBuffer(sfnt);
    assert.strictEqual(result.family, 'PartialFamily');
    assert.strictEqual(result.weight, null);
    assert.strictEqual(result.style, null);
    assert.strictEqual(result.isVariable, false);
    assert.strictEqual(result.variableKnown, true);
  });

  it('parses uncompressed WOFF name table', async () => {
    const nameTable = buildNameTable('WoffFamily');
    const numTables = 1;
    const headerSize = 44;
    const recordSize = 20;
    const tableOffset = headerSize + numTables * recordSize;

    const buf = Buffer.alloc(tableOffset + nameTable.length);
    const view = new DataView(buf.buffer, buf.byteOffset, buf.byteLength);

    // WOFF header
    buf[0] = 'w'.charCodeAt(0);
    buf[1] = 'O'.charCodeAt(0);
    buf[2] = 'F'.charCodeAt(0);
    buf[3] = 'F'.charCodeAt(0);
    view.setUint32(4, 0, false); // flavor
    view.setUint32(8, tableOffset + nameTable.length, false); // length
    view.setUint16(12, numTables, false);
    view.setUint16(14, 0, false); // reserved
    view.setUint32(16, 0, false); // totalSfntSize

    // Table directory record
    for (let i = 0; i < 4; i++) {
      buf[headerSize + i] = 'name'.charCodeAt(i);
    }
    view.setUint32(headerSize + 4, tableOffset, false); // offset
    view.setUint32(headerSize + 8, nameTable.length, false); // compLength
    view.setUint32(headerSize + 12, nameTable.length, false); // origLength
    view.setUint32(headerSize + 16, 0, false); // origChecksum

    nameTable.copy(buf, tableOffset);

    const result = await parseBuffer(buf);
    assert.strictEqual(result.family, 'WoffFamily');
    assert.strictEqual(result.variableKnown, true);
  });

  it('rejects name table strings that overflow table bounds', async () => {
    const nameBytes = utf16be('SafeFamily');
    const recordSize = 12;
    const headerSize = 6;
    const stringOffset = headerSize + recordSize;
    // Intentionally allocate only header + record, no string storage
    const buf = Buffer.alloc(headerSize + recordSize);
    const view = new DataView(buf.buffer, buf.byteOffset, buf.byteLength);

    view.setUint16(0, 0, false); // format
    view.setUint16(2, 1, false); // count
    view.setUint16(4, stringOffset, false); // stringOffset

    // Record: Windows Unicode English, nameID 1
    view.setUint16(6, 3, false); // platformID
    view.setUint16(8, 1, false); // encodingID
    view.setUint16(10, 0x0409, false); // languageID
    view.setUint16(12, 1, false); // nameID
    view.setUint16(14, nameBytes.length, false); // length
    view.setUint16(16, 0, false); // offset

    // stringOffset + offset + length exceeds tableLength (buf.length)
    const sfnt = buildSfnt([{ tag: 'name', data: buf }]);
    const result = await parseBuffer(sfnt);
    assert.strictEqual(result, null);
  });

  it('detects legacy true and typ1 signatures as ttf', async () => {
    for (const sig of ['true', 'typ1']) {
      const nameTable = buildNameTable('LegacyFamily');
      const sfnt = buildSfnt([{ tag: 'name', data: nameTable }]);
      const buf = Buffer.from(sfnt);
      for (let i = 0; i < 4; i++) {
        buf[i] = sig.charCodeAt(i);
      }
      const result = await parseBuffer(buf);
      assert.strictEqual(result.family, 'LegacyFamily', `expected family for ${sig}`);
    }
  });

  it('sanitizes family names with extra whitespace', async () => {
    const nameTable = buildNameTable('  Extra   Spaces  ');
    const sfnt = buildSfnt([{ tag: 'name', data: nameTable }]);
    const result = await parseBuffer(sfnt);
    assert.strictEqual(result.family, 'Extra Spaces');
  });

  it('caps family name length', async () => {
    const longName = 'A'.repeat(200);
    const nameTable = buildNameTable(longName);
    const sfnt = buildSfnt([{ tag: 'name', data: nameTable }]);
    const result = await parseBuffer(sfnt);
    assert.strictEqual(result.family.length, 100);
  });

  it('strips trailing asterisks from extracted family names', async () => {
    const nameTable = buildNameTable('  Jost***  ');
    const sfnt = buildSfnt([{ tag: 'name', data: nameTable }]);
    const result = await parseBuffer(sfnt);
    assert.strictEqual(result.family, 'Jost');
  });

  it('preserves internal asterisks in family names', async () => {
    const nameTable = buildNameTable('A* Family');
    const sfnt = buildSfnt([{ tag: 'name', data: nameTable }]);
    const result = await parseBuffer(sfnt);
    assert.strictEqual(result.family, 'A* Family');
  });
});
