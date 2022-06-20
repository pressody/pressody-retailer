import { data, dataControls, url } from '../utils/index.js'

const {dispatch, createReduxStore, register, select} = data
const {apiFetch, controls} = dataControls
const {addQueryArgs} = url

const STORE_KEY = 'pressody_retailer/composition'

const DEFAUPD_STATE = {
	solutions: [],
	parts: [],
	composerJson: {},
	postId: null,
	hashId: null,
	encryptedPDDetails: null,
	solutionIds: [],
	solutionContexts: [],
}

const compareByName = (a, b) => {
	if (a.name < b.name) {
		return -1
	}

	if (a.name > b.name) {
		return 1
	}

	return 0
}

function setSolutions (solutions) {
	return {
		type: 'SET_SOLUTIONS',
		solutions: solutions.sort(compareByName)
	}
}

function* getSolutions () {
	const solutionIds = select(STORE_KEY).getSolutionIds()
	const solutionContexts = select(STORE_KEY).getSolutionContexts()
	const solutions = yield apiFetch({
		path: addQueryArgs('/pressody_retailer/v1/solutions/processed', {
			postId: solutionIds,
			solutionsContext: solutionContexts,
		}),
		method: 'GET'
	})
	dispatch(STORE_KEY).setSolutions(solutions.sort(compareByName))
}

function setParts (parts) {
	return {
		type: 'SET_PARTS',
		parts: parts.sort(compareByName)
	}
}

function* getParts () {
	const solutionIds = select(STORE_KEY).getSolutionIds()
	const solutionContexts = select(STORE_KEY).getSolutionContexts()
	const parts = yield apiFetch({
		path: addQueryArgs('/pressody_retailer/v1/solutions/parts', {
			postId: solutionIds,
			solutionsContext: solutionContexts,
		}),
		method: 'GET',
	})
	dispatch(STORE_KEY).setParts(parts.sort(compareByName))

	// Since we need the parts to get a composer.json, do it here.
	// I haven't managed to figure it out how to do resolvers in a cascade. This will do for now.

	const encryptedPDDetails = select(STORE_KEY).getEncryptedPDDetails()
	// If we don't have the encrypted PD data, the request will be rejected either way. So, best not to waste any time on it.
	if (!encryptedPDDetails) {
		dispatch(STORE_KEY).setComposerJson({})
	}

	// noinspection JSUnresolvedVariable,JSHint
	const {
		pdrecordsCompositionsUrl,
		pdrecordsApiKey,
		pdrecordsApiPwd
	} = _pressodyRetailerEditCompositionData

	const composerJson = yield apiFetch({
		url: pdrecordsCompositionsUrl,
		method: 'POST',
		headers: {
			'Authorization': 'Basic ' + btoa(pdrecordsApiKey + ':' + pdrecordsApiPwd)
		},
		data: {
			pddetails: encryptedPDDetails,
			require: parts,
			composer: {
				'name': 'pressody/' + select(STORE_KEY).getHashId().toLowerCase(),
			}
		}
	})
	dispatch(STORE_KEY).setComposerJson(composerJson)
}

function setComposerJson (composerJson) {
	return {
		type: 'SET_COMPOSER_JSON',
		composerJson: composerJson
	}
}

function setPostId (postId) {
	return {
		type: 'SET_POST_ID',
		postId: postId,
	}
}

function setHashId (hashId) {
	return {
		type: 'SET_HASH_ID',
		hashId: hashId,
	}
}

function setEncryptedPDDetails (encryptedPDDetails) {
	return {
		type: 'SET_ENCRYPTED_PDDETAILS',
		encryptedPDDetails: encryptedPDDetails,
	}
}

function setSolutionIds (solutionIds) {
	return {
		type: 'SET_SOLUTION_IDS',
		solutionIds: solutionIds,
	}
}

function setSolutionContexts (solutionContexts) {
	return {
		type: 'SET_SOLUTION_CONTEXTS',
		solutionContexts: solutionContexts,
	}
}

const store = createReduxStore(STORE_KEY, {
	reducer (state = DEFAUPD_STATE, action) {
		switch (action.type) {
			case 'SET_SOLUTIONS' :
				return {
					...state,
					solutions: action.solutions,
				}

			case 'SET_PARTS' :
				return {
					...state,
					parts: action.parts,
				}

			case 'SET_COMPOSER_JSON' :
				return {
					...state,
					composerJson: action.composerJson,
				}

			case 'SET_POST_ID' :
				return {
					...state,
					postId: action.postId,
				}

			case 'SET_HASH_ID' :
				return {
					...state,
					hashId: action.hashId,
				}

			case 'SET_ENCRYPTED_PDDETAILS' :
				return {
					...state,
					encryptedPDDetails: action.encryptedPDDetails,
				}

			case 'SET_SOLUTION_IDS' :
				return {
					...state,
					solutionIds: action.solutionIds,
				}

			case 'SET_SOLUTION_CONTEXTS' :
				return {
					...state,
					solutionContexts: action.solutionContexts,
				}
		}

		return state
	},
	actions: {
		setSolutions,
		setParts,
		setComposerJson,
		setPostId,
		setHashId,
		setEncryptedPDDetails,
		setSolutionIds,
		setSolutionContexts,
	},
	selectors: {
		getSolutions (state) {
			return state.solutions || []
		},
		getParts (state) {
			return state.parts || []
		},
		getComposerJson (state) {
			return state.composerJson || {}
		},
		getPostId (state) {
			return state.postId || null
		},
		getHashId (state) {
			return state.hashId || null
		},
		getEncryptedPDDetails (state) {
			return state.encryptedPDDetails || ''
		},
		getSolutionIds (state) {
			return state.solutionIds || []
		},
		getSolutionContexts (state) {
			return state.solutionContexts || []
		},
	},
	resolvers: {
		getSolutions,
		getParts,
	},
	controls,
} )
register(store)
