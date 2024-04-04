/**
 * Styles
 */
import './style.scss';

/**
 * External dependencies
 */
import clsx from 'clsx';
import { SwitchTransition, CSSTransition } from 'react-transition-group';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { render, useEffect, useRef } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import './store/admin';
import './store/settings';
import './store/rules';
import './store/logs';

import pages from './pages';
import { ReactComponent as RedirectTxtLogoIcon } from '../icons/redirect-txt-logo.svg';

function PageWrapper() {
	const transitionRef = useRef();
	const { setActivePage } = useDispatch('redirect-txt/admin');

	const { activePage } = useSelect((select) => {
		const { getActivePage } = select('redirect-txt/admin');

		return {
			activePage: getActivePage(),
		};
	});

	// Highlight active links and change browser history.
	useEffect(() => {
		// Change body class.
		document.body.classList.forEach((className) => {
			if (/redirect-txt-admin-page-/.test(className)) {
				document.body.classList.remove(className);
			}
		});
		document.body.classList.add(`redirect-txt-admin-page-${activePage}`);

		// change address bar link
		window.history.pushState(
			document.title,
			document.title,
			`tools.php?page=redirect-txt&sub_page=${activePage}`
		);
	}, [activePage]);

	const resultTabs = [];
	let resultContent = '';

	Object.keys(pages).forEach((k) => {
		resultTabs.push(
			<li key={k}>
				<a
					href={`tools.php?page=redirect-txt&sub_page=${k}`}
					className={clsx(
						'redirect-txt-admin-tabs-button',
						activePage === k &&
							'redirect-txt-admin-tabs-button-active'
					)}
					onClick={(e) => {
						if (pages[k] && !pages[k].href) {
							e.preventDefault();
							setActivePage(k);
						}
					}}
				>
					{pages[k].label}
				</a>
			</li>
		);
	});

	if (activePage && pages[activePage]) {
		const NewBlock = pages[activePage].block;

		resultContent = <NewBlock />;
	}

	return (
		<>
			<div className="redirect-txt-admin-head">
				<div className="redirect-txt-admin-head-container">
					<div className="redirect-txt-admin-head-logo">
						<RedirectTxtLogoIcon />
						<h1>{__('Redirect.txt', 'redirect-txt')}</h1>
					</div>
					<ul className="redirect-txt-admin-tabs">{resultTabs}</ul>
				</div>
			</div>
			<SwitchTransition mode="out-in">
				<CSSTransition
					key={activePage}
					nodeRef={transitionRef}
					addEndListener={(done) => {
						transitionRef.current.addEventListener(
							'transitionend',
							done,
							false
						);
					}}
					classNames="redirect-txt-admin-content-transition"
				>
					<div
						ref={transitionRef}
						className={clsx(
							'redirect-txt-admin-content',
							`redirect-txt-admin-content-${activePage}`
						)}
					>
						{resultContent}
					</div>
				</CSSTransition>
			</SwitchTransition>
		</>
	);
}

window.addEventListener('load', () => {
	render(<PageWrapper />, document.querySelector('.redirect-txt-admin-root'));
});
