import TimeAgo from 'javascript-time-ago';
import en from 'javascript-time-ago/locale/en';

import ReactTimeAgo from 'react-time-ago';

TimeAgo.addDefaultLocale(en);

const TooltipContainer = ({ verboseDate, children }) => (
	<>
		{children}
		<br />
		<span>{verboseDate}</span>
	</>
);

export default function TimeAgoComponent(props) {
	const { date } = props;

	return (
		<ReactTimeAgo
			date={date}
			tooltip={false}
			wrapperComponent={TooltipContainer}
			formatVerboseDate={(currentDate) =>
				new Intl.DateTimeFormat(undefined, {
					year: 'numeric',
					month: 'long',
					day: 'numeric',
					hour: 'numeric',
					minute: 'numeric',
					second: 'numeric',
					hour12: false,
				}).format(currentDate)
			}
		/>
	);
}
