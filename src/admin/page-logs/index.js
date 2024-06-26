/**
 * Styles
 */
import './style.scss';

import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import clsx from 'clsx';
import TimeAgo from '../../components/time-ago';

const { adminUrl } = window.redirectTxtAdminData;

export default function PageLogs() {
	const { settings } = useSelect((select) => {
		const settingsSelect = select('redirect-txt/settings');

		return {
			settings: settingsSelect.getSettings(),
		};
	});

	const enabledRedirectLogs = settings?.redirect_logs || 0;
	const enabled404Logs = settings?.['404_logs'] || 0;

	const { logs } = useSelect(
		(select) => {
			const logsSelect = select('redirect-txt/logs');

			return {
				logs: logsSelect.getLogs(enabledRedirectLogs, enabled404Logs),
			};
		},
		[enabledRedirectLogs, enabled404Logs]
	);

	return (
		<div className="redirect-txt-admin-logs-card">
			<h2>{__('Logs', 'redirect-txt')}</h2>

			{logs && logs.length ? (
				<div className="redirect-txt-admin-logs-card-table">
					<table>
						<thead>
							<tr>
								<th className="redirect-txt-admin-logs-card-table-th-status">
									{__('Status', 'redirect-txt')}
								</th>
								<th className="redirect-txt-admin-logs-card-table-th-url">
									{__('Rule / URL', 'redirect-txt')}
								</th>
								<th className="redirect-txt-admin-logs-card-table-th-referrer">
									{__('Referrer', 'redirect-txt')}
								</th>
								<th className="redirect-txt-admin-logs-card-table-th-date">
									{__('Date', 'redirect-txt')}
								</th>
							</tr>
						</thead>
						<tbody>
							{logs.map((log) => {
								const date = new Date(log.timestamp * 1000);
								const fromIsPost = log.from_type === 'id';
								const toIsPost = log.to_type === 'id';
								const hasTo =
									toIsPost || log.to_rule || log.url_to;

								return (
									<tr key={log.timestamp}>
										<th scope="row">
											<span
												className={clsx(
													'redirect-txt-admin-logs-status',
													log.status &&
														`redirect-txt-admin-logs-status-${log.status}`
												)}
											>
												{log.status}
											</span>
										</th>
										<td>
											{fromIsPost ? (
												<span className="redirect-txt-admin-logs-rule-from">
													{__(
														'Post:',
														'redirect-txt'
													)}{' '}
													<a
														className="redirect-txt-admin-logs-rule-from"
														title={__(
															'Edit post',
															'readirect-txt'
														)}
														target="_blank"
														href={`${adminUrl}/post.php?post=${log.from_rule}&action=edit`}
														rel="noreferrer"
													>
														#{log.from_rule}
													</a>
													{hasTo && <span>⤵︎</span>}
												</span>
											) : (
												<span className="redirect-txt-admin-logs-rule-from">
													{log.url_from || '-'}
													{hasTo && <span>⤵︎</span>}
												</span>
											)}
											<br />
											{toIsPost && (
												<span className="redirect-txt-admin-logs-rule-to">
													{__(
														'Post:',
														'redirect-txt'
													)}{' '}
													<a
														className="redirect-txt-admin-logs-rule-to"
														title={__(
															'Edit post',
															'readirect-txt'
														)}
														target="_blank"
														href={`${adminUrl}/post.php?post=${log.to_rule}&action=edit`}
														rel="noreferrer"
													>
														#{log.to_rule}
													</a>
												</span>
											)}
											{!toIsPost && log.url_to && (
												<span className="redirect-txt-admin-logs-rule-to">
													{log.url_to}
												</span>
											)}
										</td>
										<td>
											{log.referrer ? (
												<span className="redirect-txt-admin-logs-referrer">
													{log.referrer}
												</span>
											) : (
												'-'
											)}
										</td>
										<td>
											{date ? (
												<span className="redirect-txt-admin-logs-date">
													<TimeAgo date={date} />
												</span>
											) : (
												'-'
											)}
										</td>
									</tr>
								);
							})}
						</tbody>
					</table>
				</div>
			) : (
				<p>
					{enabledRedirectLogs || enabled404Logs
						? __('There are no logs available yet.', 'redirect-txt')
						: __(
								'Open settings and enable logs for redirects and 404 errors to start collecting them.',
								'redirect-txt'
							)}
				</p>
			)}
		</div>
	);
}
