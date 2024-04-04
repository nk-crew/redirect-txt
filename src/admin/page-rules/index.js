import './style.scss';

import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

import { ReactComponent as LoadingIcon } from '../../icons/loading.svg';
import Editor from '../../components/editor';
import Button from '../../components/button';

export default function PageRules() {
	const [pendingRules, setPendingRules] = useState('');
	const [rulesChanged, setRulesChanged] = useState(false);

	const { updateRules } = useDispatch('redirect-txt/rules');

	const { rules, updating, error } = useSelect((select) => {
		const rulesSelect = select('redirect-txt/rules');

		return {
			rules: rulesSelect.getRules(),
			updating: rulesSelect.getUpdating(),
			error: rulesSelect.getError(),
		};
	});

	// Update pending rules.
	useEffect(() => {
		setPendingRules(rules);
	}, [rules]);

	// Check if rules changed.
	useEffect(() => {
		setRulesChanged(rules !== pendingRules);
	}, [rules, pendingRules]);

	return (
		<>
			<div className="redirect-txt-admin-rules-card">
				<h2>{__('Redirect Rules', 'redirect-txt')}</h2>
				<Editor
					value={rules}
					language="yaml"
					placeholder={__('Enter rules…', 'redirect-txt')}
					onChange={(evn) => setPendingRules(evn.target.value)}
					padding={15}
				/>
				<div className="redirect-txt-admin-rules-card-description">
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
