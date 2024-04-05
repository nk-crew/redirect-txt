/**
 * Internal dependencies
 */
import reducer from './reducer';
import * as selectors from './selectors';

/**
 * WordPress dependencies
 */
import { createReduxStore, register } from '@wordpress/data';

const store = createReduxStore('redirect-txt/logs', {
	reducer,
	selectors,
});

register(store);
