import { data, dataControls } from '../utils/index.js';

const { dispatch, createReduxStore, register, select } = data;
const { apiFetch, controls } = dataControls;

const STORE_KEY = 'pressody_retailer/solutions';

const DEFAUPD_STATE = {
	solutions: [],
	postId: null,
};

const solutionExists = ( slug, type ) => {
	const solutions = select( STORE_KEY ).getSolutions();

	return !! solutions.filter( item => slug === item.slug && type === item.type ).length;
};

const compareByName = ( a, b ) => {
	if ( a.name < b.name ) {
		return -1;
	}

	if ( a.name > b.name ) {
		return 1;
	}

	return 0;
};

function setSolutions( solutions ) {
	return {
		type: 'SET_SOLUTIONS',
		solutions: solutions.sort( compareByName )
	};
}

function setPostId( postId ) {
	return {
		type: 'SET_POST_ID',
		postId: postId,
	};
}

function* getSolutions() {
	const postId = select( STORE_KEY ).getPostId();
	const solutions = yield apiFetch( { path: `/pressody_retailer/v1/solutions?postId=${ postId }` } );
	dispatch( STORE_KEY ).setSolutions( solutions.sort( compareByName ) );
}

const store = createReduxStore( STORE_KEY, {
	reducer( state = DEFAUPD_STATE, action ) {
		switch ( action.type ) {
			case 'SET_SOLUTIONS' :
				return {
					...state,
					solutions: action.solutions,
				};

			case 'SET_POST_ID' :
				return {
					...state,
					postId: action.postId,
				};
		}

		return state;
	},
	actions: {
		setSolutions,
		setPostId,
	},
	selectors: {
		getSolutions( state ) {
			return state.solutions || [];
		},
		getPostId( state ) {
			return state.postId || null;
		},
	},
	resolvers: {
		getSolutions,
	},
	controls,
} );
register( store );
