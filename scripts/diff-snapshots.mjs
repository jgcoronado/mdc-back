#!/usr/bin/env node
/**
 * Compare two snapshot directories produced by snapshot-endpoints.mjs.
 *
 * For each .json file present in both dirs the script reports:
 *   - identical: bodies match exactly
 *   - different: shape or content differs (prints a short diff)
 *   - missing: file only present in one side
 *
 * Usage:
 *   node scripts/diff-snapshots.mjs snapshots/mysql snapshots/sqlite
 */

import fs from 'node:fs';
import path from 'node:path';

const readJsonFile = (filePath) => {
  const raw = fs.readFileSync(filePath, 'utf8');
  return JSON.parse(raw);
};

const listJsonFiles = (dirPath) => {
  if (!fs.existsSync(dirPath)) return [];
  return fs.readdirSync(dirPath).filter((name) => name.endsWith('.json'));
};

const stableStringify = (value) => {
  if (value === null || typeof value !== 'object') return JSON.stringify(value);
  if (Array.isArray(value)) {
    return `[${value.map(stableStringify).join(',')}]`;
  }
  const sortedKeys = Object.keys(value).sort();
  const parts = sortedKeys.map((key) => `${JSON.stringify(key)}:${stableStringify(value[key])}`);
  return `{${parts.join(',')}}`;
};

const areBodiesEquivalent = (left, right) => {
  return stableStringify(left) === stableStringify(right);
};

const summarizeValue = (value) => {
  if (Array.isArray(value)) return `array(len=${value.length})`;
  if (value && typeof value === 'object') return `object(keys=${Object.keys(value).length})`;
  const str = String(value);
  return str.length > 80 ? `${str.slice(0, 80)}...` : str;
};

const findFirstDifference = (left, right, currentPath = '$') => {
  if (typeof left !== typeof right) {
    return `${currentPath}: type ${typeof left} vs ${typeof right}`;
  }
  if (left === null || right === null || typeof left !== 'object') {
    return left === right ? null : `${currentPath}: ${summarizeValue(left)} vs ${summarizeValue(right)}`;
  }
  if (Array.isArray(left) !== Array.isArray(right)) {
    return `${currentPath}: array vs object`;
  }
  if (Array.isArray(left)) {
    if (left.length !== right.length) {
      return `${currentPath}.length: ${left.length} vs ${right.length}`;
    }
    for (let index = 0; index < left.length; index += 1) {
      const inner = findFirstDifference(left[index], right[index], `${currentPath}[${index}]`);
      if (inner) return inner;
    }
    return null;
  }
  const allKeys = new Set([...Object.keys(left), ...Object.keys(right)]);
  for (const key of allKeys) {
    const inner = findFirstDifference(left[key], right[key], `${currentPath}.${key}`);
    if (inner) return inner;
  }
  return null;
};

const compareSnapshots = (leftDir, rightDir) => {
  const leftFiles = new Set(listJsonFiles(leftDir));
  const rightFiles = new Set(listJsonFiles(rightDir));
  const allFiles = new Set([...leftFiles, ...rightFiles]);

  const stats = { equal: 0, different: 0, missing: 0 };

  for (const fileName of [...allFiles].sort()) {
    if (!leftFiles.has(fileName)) {
      console.log(`[MISSING-LEFT ] ${fileName}`);
      stats.missing += 1;
      continue;
    }
    if (!rightFiles.has(fileName)) {
      console.log(`[MISSING-RIGHT] ${fileName}`);
      stats.missing += 1;
      continue;
    }
    const left = readJsonFile(path.join(leftDir, fileName));
    const right = readJsonFile(path.join(rightDir, fileName));
    if (areBodiesEquivalent(left, right)) {
      stats.equal += 1;
      continue;
    }
    const diff = findFirstDifference(left, right);
    console.log(`[DIFF         ] ${fileName} -> ${diff}`);
    stats.different += 1;
  }

  console.log(`\nSummary: ${stats.equal} equal, ${stats.different} different, ${stats.missing} missing.`);
  return stats;
};

const main = () => {
  const [leftDir, rightDir] = process.argv.slice(2);
  if (!leftDir || !rightDir) {
    console.error('Usage: node scripts/diff-snapshots.mjs <leftDir> <rightDir>');
    process.exit(2);
  }
  const stats = compareSnapshots(leftDir, rightDir);
  if (stats.different > 0 || stats.missing > 0) {
    process.exit(1);
  }
};

main();
