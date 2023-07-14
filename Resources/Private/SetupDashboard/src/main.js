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
	errorHTML: false,
	reloadTimeout: null,
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
	async fetch(type) {
		const response = await fetch(`/setup/${type}.json`);
		let data = await response.text();
		try {
			// Check if the response is valid JSON
			data = JSON.parse(data);
			if (response.status === 503) {
				if (type === 'compiletime') {
					this.checks = { ...data };
				} else {
					this.checks = { ...this.checks, ...data };
				}
				return false;
			}
			this.checks = { ...this.checks, ...data };
			return true;
		} catch (error) {
			this.errorHTML = data;
			if (this.reloadTimeout) {
				this.reloadTimeout.clear();
			}
			return false;
		}
	},
	async runChecks() {
		if (await this.fetch('compiletime')) {
			await this.fetch('runtime');
		}
	},
	async init() {
		this.runChecks();
		this.reloadTimeout = rafInterval(()=>{this.runChecks()}, 10000);
	}
}))

Alpine.start()
