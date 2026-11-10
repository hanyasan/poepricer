// Checks the translation keys: every key used in app/ exists in every locale, and the
// locales have the same set of keys. Unused keys are only reported.
// Usage: node scripts/check-i18n.mjs
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'

const root = new URL('..', import.meta.url).pathname
const localeDir = join(root, 'i18n/locales')
const sourceDir = join(root, 'app')

function flatten(object, prefix = '') {
  return Object.entries(object).flatMap(([key, value]) =>
    typeof value === 'object' && value !== null ? flatten(value, `${prefix}${key}.`) : [`${prefix}${key}`],
  )
}

function listFiles(dir) {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)
    if (statSync(path).isDirectory()) return listFiles(path)
    return /\.(vue|ts)$/.test(name) ? [path] : []
  })
}

const locales = Object.fromEntries(
  readdirSync(localeDir)
    .filter((file) => file.endsWith('.json'))
    .map((file) => [file.replace('.json', ''), new Set(flatten(JSON.parse(readFileSync(join(localeDir, file), 'utf8'))))]),
)

// A call `t(...)`, `$t(...)`, `te(...)`, `tm(...)` or an `keypath="..."` attribute. For a call
// every string literal that looks like a key is taken, which also covers ternaries.
const callStartPattern = /(?<![\w.])\$?(?:t|te|tm)\(/g
const literalPattern = /(['"`])([A-Za-z][\w-]*(?:\.[\w${}.-]+)+)\1/g
const keypathPattern = /keypath=["']([^"']+)["']/g

/**
 * The arguments of the call that starts at `start`, found by counting parentheses instead of
 * matching them with a pattern: an argument may hold nested calls such as Math.round(count / total).
 */
function callArguments(source, start) {
  let depth = 0

  for (let index = start; index < source.length; index++) {
    if (source[index] === '(') depth++
    if (source[index] === ')') {
      depth--
      if (depth === 0) return source.slice(start + 1, index)
    }
  }

  return ''
}

const used = new Map()
for (const file of listFiles(sourceDir)) {
  const source = readFileSync(file, 'utf8')
  const keys = []
  for (const call of source.matchAll(callStartPattern)) {
    const argumentsText = callArguments(source, call.index + call[0].length - 1)
    for (const literal of argumentsText.matchAll(literalPattern)) keys.push(literal[2])
  }
  for (const match of source.matchAll(keypathPattern)) keys.push(match[1])
  for (const key of keys) {
    if (!used.has(key)) used.set(key, new Set())
    used.get(key).add(relative(root, file))
  }
}

// A key with ${...} is dynamic: it matches every locale key with that prefix.
const toPattern = (key) => new RegExp(`^${key.split(/\$\{[^}]*\}/).map((part) => part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('[^.]+')}$`)

const errors = []
for (const [name, keys] of Object.entries(locales)) {
  for (const [key, files] of used) {
    const found = key.includes('${') ? [...keys].some((existing) => toPattern(key).test(existing)) : keys.has(key)
    if (!found) errors.push(`[${name}] missing "${key}" used in ${[...files].join(', ')}`)
  }
}

const names = Object.keys(locales)
for (const name of names) {
  for (const other of names.filter((candidate) => candidate !== name)) {
    for (const key of locales[name]) {
      if (!locales[other].has(key)) errors.push(`[${other}] missing "${key}" that exists in ${name}`)
    }
  }
}

const patterns = [...used.keys()].map((key) => (key.includes('${') ? toPattern(key) : null))
const usedKeys = new Set(used.keys())
const reference = locales[names[0]]
const unused = [...reference].filter((key) => !usedKeys.has(key) && !patterns.some((pattern) => pattern?.test(key)))
if (unused.length > 0) {
  console.warn(`Unused keys (${unused.length}), not an error:\n${unused.map((key) => `  ${key}`).join('\n')}`)
}

if (errors.length > 0) {
  console.error(errors.join('\n'))
  process.exit(1)
}
console.log(`i18n OK: ${used.size} used keys, ${names.join(', ')} are in sync`)
