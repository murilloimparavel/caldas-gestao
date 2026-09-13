/**
 * Pure, framework-free helpers backing the Romawear booking demo.
 * Exposed on window for the static page and exported for Node tests.
 */
(function (root) {
	'use strict';

	var PROFESSIONAL = 'Bruno Roma';

	var SERVICES = [
		{ id: 'corte', name: 'Corte', category: 'Cabelo', priceCents: 3500, durationMin: 40, featured: true, imageUrl: '/romawear-demo/assets/corte.webp', description: null },
		{ id: 'barba', name: 'Barba', category: 'Barba', priceCents: 3500, durationMin: 30, featured: true, imageUrl: '/romawear-demo/assets/barba.webp', description: null },
		{ id: 'sobrancelha', name: 'Sobrancelha', category: 'Design', priceCents: 1000, durationMin: 10, featured: true, imageUrl: '/romawear-demo/assets/sobrancelha.webp', description: null },
		{ id: 'pigmentacao', name: 'Pigmentação', category: 'Cabelo', priceCents: 2500, durationMin: 25, featured: false },
		{ id: 'depilacao-nariz', name: 'Depilação Nariz', category: 'Depilação', priceCents: 2500, durationMin: 20, featured: false },
		{ id: 'depilacao-nariz-orelha', name: 'Depilação Nariz + Orelha', category: 'Depilação', priceCents: 4000, durationMin: 30, featured: false },
		{ id: 'botox-capilar', name: 'Botox Capilar', category: 'Tratamento', priceCents: 9000, durationMin: 60, featured: false },
		{ id: 'selagem', name: 'Selagem', category: 'Tratamento', priceCents: 12000, durationMin: 60, featured: false },
	];

	var ILLUSTRATIVE_TIME_SLOTS = ['09:00', '10:30', '13:00', '14:30', '16:00', '17:30'];

	var WEEKDAY_LABELS = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

	/**
	 * @param {Array<object>} services
	 * @returns {Array<object>}
	 */
	function featuredServices(services) {
		return services.filter(function (service) {
			return service.featured;
		});
	}

	/**
	 * @param {Array<object>} services
	 * @returns {Array<object>}
	 */
	function otherServices(services) {
		return services.filter(function (service) {
			return !service.featured;
		});
	}

	/**
	 * @param {string} query
	 * @param {string} category
	 * @returns {Array<object>}
	 */
	function filterServices(services, query, category) {
		var normalizedQuery = (query || '').trim().toLowerCase();

		return services.filter(function (service) {
			var matchesCategory = category === 'Todos' || service.category === category;
			var matchesQuery = normalizedQuery === '' || service.name.toLowerCase().indexOf(normalizedQuery) !== -1;

			return matchesCategory && matchesQuery;
		});
	}

	/**
	 * @param {string[]} selectedIds
	 * @param {string} serviceId
	 * @returns {string[]}
	 */
	function toggleSelection(selectedIds, serviceId) {
		if (selectedIds.indexOf(serviceId) !== -1) {
			return selectedIds.filter(function (id) {
				return id !== serviceId;
			});
		}

		return selectedIds.concat([serviceId]);
	}

	/**
	 * @param {string[]} selectedIds
	 * @param {string} serviceId
	 * @returns {string[]}
	 */
	function removeSelection(selectedIds, serviceId) {
		return selectedIds.filter(function (id) {
			return id !== serviceId;
		});
	}

	/**
	 * @param {Array<object>} services
	 * @param {string[]} selectedIds
	 * @returns {{totalCents: number, totalDurationMin: number, count: number}}
	 */
	function summarize(services, selectedIds) {
		var selectedSet = services.filter(function (service) {
			return selectedIds.indexOf(service.id) !== -1;
		});

		return selectedSet.reduce(
			function (acc, service) {
				acc.totalCents += service.priceCents;
				acc.totalDurationMin += service.durationMin;
				acc.count += 1;

				return acc;
			},
			{ totalCents: 0, totalDurationMin: 0, count: 0 }
		);
	}

	/**
	 * @param {number} cents
	 * @returns {string}
	 */
	function formatBRL(cents) {
		return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
	}

	/**
	 * @param {number} totalMinutes
	 * @returns {string}
	 */
	function formatDuration(totalMinutes) {
		var hours = Math.floor(totalMinutes / 60);
		var minutes = totalMinutes % 60;

		if (hours === 0) {
			return minutes + ' min';
		}

		if (minutes === 0) {
			return hours + 'h';
		}

		return hours + 'h' + minutes + 'min';
	}

	/**
	 * @returns {Array<{category: string}>}
	 */
	function categoriesFrom(services) {
		var seen = [];

		services.forEach(function (service) {
			if (seen.indexOf(service.category) === -1) {
				seen.push(service.category);
			}
		});

		return ['Todos'].concat(seen);
	}

	/**
	 * Builds a list of illustrative upcoming days for the demo date picker.
	 * This does not read or write any real calendar/availability data.
	 * @param {Date} referenceDate
	 * @param {number} count
	 * @returns {Array<{iso: string, weekday: string, day: number, month: number}>}
	 */
	function upcomingDays(referenceDate, count) {
		var days = [];

		for (var i = 0; i < count; i += 1) {
			var date = new Date(referenceDate.getTime());
			date.setDate(date.getDate() + i);

			days.push({
				iso: date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()),
				weekday: WEEKDAY_LABELS[date.getDay()],
				day: date.getDate(),
				month: date.getMonth() + 1,
			});
		}

		return days;
	}

	/**
	 * @param {number} value
	 * @returns {string}
	 */
	function pad(value) {
		return value < 10 ? '0' + value : String(value);
	}

	/**
	 * @param {string} dateIso
	 * @param {string} time
	 * @param {Array<object>} services
	 * @param {string[]} selectedIds
	 * @returns {string}
	 */
	function buildWhatsappMessage(dateIso, time, services, selectedIds, customerName) {
		var summary = summarize(services, selectedIds);
		var names = services
			.filter(function (service) {
				return selectedIds.indexOf(service.id) !== -1;
			})
			.map(function (service) {
				return service.name;
			});

		var lines = [
			'Olá! Gostaria de agendar um horário com ' + PROFESSIONAL + '.',
			'Serviços: ' + (names.length ? names.join(', ') : '—'),
			'Duração estimada: ' + formatDuration(summary.totalDurationMin),
			'Total estimado: ' + formatBRL(summary.totalCents),
			'Horário desejado: ' + (dateIso || '—') + ' às ' + (time || '—'),
		];

		if (customerName) {
			lines.push('Nome: ' + customerName);
		}

		lines.push('(Este horário ainda não foi reservado nem confirmado — é apenas um pedido de agendamento.)');

		return lines.join('\n');
	}

	var api = {
		PROFESSIONAL: PROFESSIONAL,
		SERVICES: SERVICES,
		ILLUSTRATIVE_TIME_SLOTS: ILLUSTRATIVE_TIME_SLOTS,
		featuredServices: featuredServices,
		otherServices: otherServices,
		filterServices: filterServices,
		toggleSelection: toggleSelection,
		removeSelection: removeSelection,
		summarize: summarize,
		formatBRL: formatBRL,
		formatDuration: formatDuration,
		categoriesFrom: categoriesFrom,
		upcomingDays: upcomingDays,
		buildWhatsappMessage: buildWhatsappMessage,
	};

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	} else {
		root.RomawearBooking = api;
	}
})(typeof window !== 'undefined' ? window : globalThis);
