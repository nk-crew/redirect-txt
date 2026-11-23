import './style.scss';

import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

import { ReactComponent as LoadingIcon } from '../../icons/loading.svg';
import RuleEditor from './rule-editor';
import Button from '../../components/button';

const isMac = window.navigator.platform.indexOf('Mac') > -1;

export default function PageRules() {
	const [savingRules, setSavingRules] = useState(false);

	const { updateRules } = useDispatch('redirect-txt/rules');

	const { rules, updating, error } = useSelect((select) => {
		const rulesSelect = select('redirect-txt/rules');

		return {
			rules: rulesSelect.getRules(),
			updating: rulesSelect.getUpdating(),
			error: rulesSelect.getError(),
		};
	});

	const [pendingRules, setPendingRules] = useState(rules);
	const rulesChanged = rules !== pendingRules;

	// Save when CMD+S pressed.
	useEffect(() => {
		if (savingRules) {
			setSavingRules(false);

			if (rulesChanged) {
				updateRules(pendingRules);
			}
		}
	}, [pendingRules, rulesChanged, savingRules, updateRules]);

	useEffect(() => {
		const handle = (e) => {
			const metaKey = isMac ? e.metaKey : e.ctrlKey;

			if (e.key === 's' && metaKey) {
				e.preventDefault();
				setSavingRules(true);
			}
		};

		document.addEventListener('keydown', handle);
		return () => document.removeEventListener('keydown', handle);
	}, []);

	return (
		<>
			<div className="redirect-txt-admin-rules-card">
				<h2>{__('Redirect Rules', 'redirect-txt')}</h2>
				<RuleEditor rules={pendingRules} onChange={setPendingRules} />
				<div
					className="redirect-txt-admin-rules-card-description"
					id="redirect-txt-rules-help"
				>
					{__(
						'Provide a list of all your redirect rules. Use a yaml-like format. Each redirect should be in a new line. Each redirect should have a source and a target separated by a colon. For example: /old-url: /new-url',
						'redirect-txt'
					)}
				</div>
			</div>
			{error && (
				<div className="redirect-txt-admin-rules-error">{error}</div>
			)}
			<div className="redirect-txt-admin-rules-actions">
				<Button
					disabled={!rulesChanged}
					onClick={(e) => {
						e.preventDefault();

						updateRules(pendingRules);
					}}
				>
					{__('Save Changes', 'redirect-txt')}
					{updating && <LoadingIcon viewBox="0 0 24 24" />}
				</Button>
			</div>
		</>
	);
}
