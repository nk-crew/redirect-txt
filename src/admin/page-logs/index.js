/**
 * Styles
 */
import './style.scss';

import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import clsx from 'clsx';

const { adminUrl } = window.redirectTxtAdminData;

export default function PageLogs() {
	const { logs } = useSelect((select) => {
		const logsSelect = select('redirect-txt/logs');

		return {
			logs: logsSelect.getLogs(),
		};
	});

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
								const fromIsPost =
									log.rule_from && !isNaN(log.rule_from);
								const toIsPost =
									log.rule_to && !isNaN(log.rule_to);
								const hasTo =
									toIsPost || log.rule_to || log.url_to;

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
														href={`${adminUrl}/post.php?post=${log.rule_from}&action=edit`}
														rel="noreferrer"
													>
														#{log.rule_from}
													</a>
													{hasTo && <span>⤵︎</span>}
												</span>
											) : (
												<span className="redirect-txt-admin-logs-rule-from">
													{log.rule_from ||
														log.url_from ||
														'-'}
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
														href={`${adminUrl}/post.php?post=${log.rule_to}&action=edit`}
														rel="noreferrer"
													>
														#{log.rule_to}
													</a>
												</span>
											)}
											{!toIsPost &&
												(log.rule_to || log.url_to) && (
													<span className="redirect-txt-admin-logs-rule-to">
														{log.rule_to ||
															log.url_to}
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
													{date.toLocaleString(
														'en-US'
													)}
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
				<p>{__('There are no logs available yet.', 'redirect-txt')}</p>
			)}
		</div>
	);
}
