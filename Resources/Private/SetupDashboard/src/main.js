import Alpine from 'alpinejs'

const dateNow = Date.now;
const raf = window.requestAnimationFrame;
const rafInterval = (callback, delay) => {
	let start = dateNow();
	let stop = false;
	const intervalFunc = () => {
		dateNow() - start < delay || ((start += delay), callback());
		stop || raf(intervalFunc);
	};
	raf(intervalFunc);
	return {
		clear: () => (stop = true),
	};
};

const rafTimeOut = (callback, delay) => {
	let start = dateNow();
	let stop = false;
	const timeoutFunc = () => {
		dateNow() - start < delay ? stop || raf(timeoutFunc) : callback();
	};
	raf(timeoutFunc);
	return {
		clear: () => (stop = true),
	};
};

Alpine.data('health', () => ({
	cssClasses: {
		'OK': 'bg-green-700',
		'WARNING': 'bg-yellow-700',
		'ERROR': 'bg-red-700',
		'UNKNOWN': 'bg-[#323232]',
		'NOT_RUN': 'bg-[#323232]',
	},
	checks: {},
	canCopy: window.navigator.clipboard !== undefined,
	copiedCode: false,
	copyTimeout: null,
	copy() {
		this.copiedCode = [...this.$el.querySelectorAll('code')].map(el => el.innerText).join("\n");
		window.navigator.clipboard.writeText(this.copiedCode);
		if (this.copyTimeout) {
			this.copyTimeout.clear();
		}
		this.copyTimeout = rafTimeOut(() => {
			this.copiedCode = false;
		}, 5000);
	},
	fetch(type) {
		fetch(`/setup/${type}.json`).then(response => {
			if (!response.ok && response.status !== 503) {
				return {
					errorResponse: {
						status: 'ERROR',
						title: 'Flow Framework',
						message: "Flow didn't respond as expected."
					}
				}
			}
			return response.json()
		}).then(data => {
			this.checks = { ...this.checks, ...data };
		});
	},
	init() {
		this.fetch('compiletime');
		this.fetch('runtime');

		rafInterval(() => {
			this.fetch('compiletime');
			this.fetch('runtime');
		}, 5000);
	}
}))

Alpine.start()
