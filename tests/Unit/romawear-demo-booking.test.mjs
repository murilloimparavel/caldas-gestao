import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const bookingPath = path.resolve(
	path.dirname(fileURLToPath(import.meta.url)),
	'../../public/romawear-demo/booking.js'
);
const source = fs.readFileSync(bookingPath, 'utf8');
const sandbox = { module: { exports: {} }, globalThis: undefined };
sandbox.globalThis = sandbox;
vm.createContext(sandbox);
vm.runInContext(source, sandbox);
const booking = sandbox.module.exports;

test('featuredServices returns only Corte, Barba and Sobrancelha', () => {
	const result = booking.featuredServices(booking.SERVICES);
	assert.equal(result.map((s) => s.id).sort().join(','), ['barba', 'corte', 'sobrancelha'].join(','));
});

test('featuredServices matches real catalog prices and durations', () => {
	const result = booking.featuredServices(booking.SERVICES);
	const byId = Object.fromEntries(result.map((s) => [s.id, s]));

	assert.equal(byId.corte.priceCents, 3500);
	assert.equal(byId.corte.durationMin, 40);
	assert.equal(byId.barba.priceCents, 3500);
	assert.equal(byId.barba.durationMin, 30);
	assert.equal(byId.sobrancelha.priceCents, 1000);
	assert.equal(byId.sobrancelha.durationMin, 10);
});

test('otherServices excludes the featured services', () => {
	const result = booking.otherServices(booking.SERVICES);
	assert.ok(!result.some((s) => ['corte', 'barba', 'sobrancelha'].includes(s.id)));
	assert.equal(result.length, booking.SERVICES.length - 3);
});

test('filterServices matches by category', () => {
	const result = booking.filterServices(booking.SERVICES, '', 'Depilação');
	assert.equal(result.length, 2);
	assert.ok(result.every((s) => s.category === 'Depilação'));
});

test('filterServices matches by case-insensitive query', () => {
	const result = booking.filterServices(booking.SERVICES, 'BOTOX', 'Todos');
	assert.equal(result.length, 1);
	assert.equal(result[0].id, 'botox-capilar');
});

test('filterServices returns empty array when nothing matches', () => {
	const result = booking.filterServices(booking.SERVICES, 'inexistente', 'Todos');
	assert.equal(result.length, 0);
});

test('toggleSelection adds an unselected service', () => {
	const result = booking.toggleSelection([], 'corte');
	assert.deepEqual(result, ['corte']);
});

test('toggleSelection removes an already selected service', () => {
	const result = booking.toggleSelection(['corte', 'barba'], 'corte');
	assert.deepEqual(result, ['barba']);
});

test('removeSelection removes only the given id', () => {
	const result = booking.removeSelection(['corte', 'barba', 'selagem'], 'barba');
	assert.deepEqual(result, ['corte', 'selagem']);
});

test('summarize computes totals in cents and minutes', () => {
	const summary = booking.summarize(booking.SERVICES, ['corte', 'barba']);
	assert.equal(summary.totalCents, 7000);
	assert.equal(summary.totalDurationMin, 70);
	assert.equal(summary.count, 2);
});

test('summarize returns zeroed totals for empty selection', () => {
	const summary = booking.summarize(booking.SERVICES, []);
	assert.equal(summary.totalCents, 0);
	assert.equal(summary.totalDurationMin, 0);
	assert.equal(summary.count, 0);
});

test('summarize resets to zero after a selection is cleared via removeSelection', () => {
	let selected = booking.toggleSelection([], 'corte');
	selected = booking.toggleSelection(selected, 'barba');
	assert.equal(booking.summarize(booking.SERVICES, selected).totalCents, 7000);

	selected = booking.removeSelection(selected, 'corte');
	selected = booking.removeSelection(selected, 'barba');

	const summary = booking.summarize(booking.SERVICES, selected);
	assert.equal(summary.totalCents, 0);
	assert.equal(summary.totalDurationMin, 0);
	assert.equal(summary.count, 0);
});

test('formatBRL formats cents as Brazilian currency', () => {
	assert.match(booking.formatBRL(3500), /^R\$\s*35,00$/);
});

test('formatDuration formats minutes, hours, and mixed durations', () => {
	assert.equal(booking.formatDuration(40), '40 min');
	assert.equal(booking.formatDuration(60), '1h');
	assert.equal(booking.formatDuration(70), '1h10min');
});

test('categoriesFrom prefixes Todos and de-duplicates categories', () => {
	const categories = booking.categoriesFrom(booking.SERVICES);
	assert.equal(categories[0], 'Todos');
	assert.equal(new Set(categories).size, categories.length);
});

test('upcomingDays returns the requested count starting from the reference date', () => {
	const days = booking.upcomingDays(new Date(2026, 0, 1), 5);
	assert.equal(days.length, 5);
	assert.equal(days[0].iso, '2026-01-01');
	assert.equal(days[4].iso, '2026-01-05');
});

test('buildWhatsappMessage includes Gostaria de agendar phrasing and never claims a reservation', () => {
	const message = booking.buildWhatsappMessage('2026-01-02', '09:00', booking.SERVICES, ['corte', 'barba'], 'Maria');

	assert.match(message, /Gostaria de agendar/);
	assert.match(message, /Horário desejado: 2026-01-02 às 09:00/);
	assert.match(message, /Corte/);
	assert.match(message, /Barba/);
	assert.match(message, /Maria/);
	assert.doesNotMatch(message, /^(?!.*não foi).*\breservado\b/im);
	assert.match(message, /não foi reservado nem confirmado/);
});

test('buildWhatsappMessage reflects an empty selection without totals from stale state', () => {
	const message = booking.buildWhatsappMessage('2026-01-02', '09:00', booking.SERVICES, [], 'Maria');
	assert.match(message, /Serviços: —/);
	assert.match(message, /Total estimado: R\$\s*0,00/);
});

test('featured services expose absolute /romawear-demo/assets image paths', () => {
	const result = booking.featuredServices(booking.SERVICES);
	result.forEach((service) => {
		assert.match(service.imageUrl, /^\/romawear-demo\/assets\//);
	});
});

test('featured services without a fabricated description expose description as null', () => {
	booking.featuredServices(booking.SERVICES).forEach((service) => {
		assert.ok(service.description === null || typeof service.description === 'string');
	});
});

test('isValidBirthDate accepts an empty value', () => {
	assert.equal(booking.isValidBirthDate('', new Date(2026, 8, 12)), true);
});

test('isValidBirthDate accepts a past date', () => {
	assert.equal(booking.isValidBirthDate('1990-05-20', new Date(2026, 8, 12)), true);
});

test('isValidBirthDate rejects a future date', () => {
	assert.equal(booking.isValidBirthDate('2030-01-01', new Date(2026, 8, 12)), false);
});

test('isValidBirthDate accepts the reference date itself', () => {
	assert.equal(booking.isValidBirthDate('2026-09-12', new Date(2026, 8, 12)), true);
});

test('buildWhatsappMessage never includes a birth date even if passed as an extra argument', () => {
	const message = booking.buildWhatsappMessage('2026-01-02', '09:00', booking.SERVICES, ['corte'], 'Maria', '1990-05-20');
	assert.doesNotMatch(message, /1990-05-20/);
});

const indexHtmlPath = path.resolve(
	path.dirname(fileURLToPath(import.meta.url)),
	'../../public/romawear-demo/index.html'
);
const indexHtml = fs.readFileSync(indexHtmlPath, 'utf8');

test('index.html references booking.js via an absolute root path', () => {
	assert.match(indexHtml, /<script src="\/romawear-demo\/booking\.js"><\/script>/);
	assert.doesNotMatch(indexHtml, /<script src="booking\.js"><\/script>/);
});
