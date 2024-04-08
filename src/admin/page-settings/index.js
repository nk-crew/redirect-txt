import './style.scss';

// eslint-disable-next-line import/no-extraneous-dependencies
import { isEqual } from 'lodash';
import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

import { ReactComponent as LoadingIcon } from '../../icons/loading.svg';
import Select from '../../components/select';
import Button from '../../components/button';

export default function PageSettings() {
	const [pendingSettings, setPendingSettings] = useState({});
	const [settingsChanged, setSettingsChanged] = useState(false);

	const { updateSettings } = useDispatch('redirect-txt/settings');

	const { settings, updating, error } = useSelect((select) => {
		const settingsSelect = select('redirect-txt/settings');

		return {
			settings: settingsSelect.getSettings(),
			updating: settingsSelect.getUpdating(),
			error: settingsSelect.getError(),
		};
	});

	// Update pending settings from actual settings object.
	useEffect(() => {
		setPendingSettings(settings);
	}, [settings]);

	// Check if settings changed.
	useEffect(() => {
		setSettingsChanged(!isEqual(settings, pendingSettings));
	}, [settings, pendingSettings]);

	return (
		<>
			<div className="redirect-txt-admin-settings-card">
				<h2>{__('Settings', 'redirect-txt')}</h2>

				<div className="redirect-txt-admin-settings-single">
					<label htmlFor="redirect-txt-settings-redirect-logs">
						{__('Redirect Logs:', 'redirect-txt')}
					</label>
					<Select
						id="redirect-txt-settings-redirect-logs"
						value={`${pendingSettings.redirect_logs || 0}`}
						onChange={(e) => {
							e.preventDefault();
							setPendingSettings({
								...pendingSettings,
								redirect_logs: parseInt(e.target.value, 10),
							});
						}}
					>
						<option value="0">
							{__('No logs', 'redirect-txt')}
						</option>
						<option value="1">{__('A day', 'redirect-txt')}</option>
						<option value="7">
							{__('A week', 'redirect-txt')}
						</option>
						<option value="30">
							{__('A month', 'redirect-txt')}
						</option>
						<option value="60">
							{__('Two months', 'redirect-txt')}
						</option>
						<option value="90">
							{__('Three months', 'redirect-txt')}
						</option>
						<option value="-1">
							{__('Forever', 'redirect-txt')}
						</option>
					</Select>
					<div className="redirect-txt-admin-settings-single-description">
						{__('Time to keep redirect logs.', 'redirect-txt')}
					</div>
				</div>

				<div className="redirect-txt-admin-settings-single">
					<label htmlFor="redirect-txt-settings-404-logs">
						{__('404 Logs:', 'redirect-txt')}
					</label>
					<Select
						id="redirect-txt-settings-404-logs"
						value={`${pendingSettings['404_logs'] || 0}`}
						onChange={(e) => {
							e.preventDefault();
							setPendingSettings({
								...pendingSettings,
								'404_logs': parseInt(e.target.value, 10),
							});
						}}
					>
						<option value="0">
							{__('No logs', 'redirect-txt')}
						</option>
						<option value="1">{__('A day', 'redirect-txt')}</option>
						<option value="7">
							{__('A week', 'redirect-txt')}
						</option>
						<option value="30">
							{__('A month', 'redirect-txt')}
						</option>
						<option value="60">
							{__('Two months', 'redirect-txt')}
						</option>
						<option value="90">
							{__('Three months', 'redirect-txt')}
						</option>
						<option value="-1">
							{__('Forever', 'redirect-txt')}
						</option>
					</Select>
					<div className="redirect-txt-admin-settings-single-description">
						{__('Time to keep 404 error logs.', 'redirect-txt')}
					</div>
				</div>
			</div>
			{error && (
				<div className="redirect-txt-admin-settings-error">{error}</div>
			)}
			<div className="redirect-txt-admin-settings-actions">
				<Button
					disabled={!settingsChanged}
					onClick={(e) => {
						e.preventDefault();

						updateSettings(pendingSettings);
					}}
				>
					{__('Save Changes', 'redirect-txt')}
					{updating && <LoadingIcon viewBox="0 0 24 24" />}
				</Button>
			</div>
		</>
	);
}
