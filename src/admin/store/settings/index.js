/**
 * Internal dependencies
 */
import reducer from './reducer';
import * as actions from './actions';
import * as selectors from './selectors';

/**
 * WordPress dependencies
 */
import { createReduxStore, register } from '@wordpress/data';

const store = createReduxStore('redirect-txt/settings', {
	reducer,
	actions,
	selectors,
});

register(store);
