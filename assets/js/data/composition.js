import { data, dataControls, url } from '../utils/index.js'

const {dispatch, createReduxStore, register, select} = data
const {apiFetch, controls} = dataControls
const {addQueryArgs} = url

const STORE_KEY = 'pixelgradelt_retailer/composition'

const DEFAULT_STATE = {
	solutions: [],
	parts: [],
	composerJson: {},
	postId: null,
	hashId: null,
	encryptedUser: null,
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
		path: addQueryArgs('/pixelgradelt_retailer/v1/solutions/processed', {
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
		path: addQueryArgs('/pixelgradelt_retailer/v1/solutions/parts', {
			postId: solutionIds,
			solutionsContext: solutionContexts,
		}),
		method: 'GET',
	})
	dispatch(STORE_KEY).setParts(parts.sort(compareByName))

	// Since we need the parts to get a composer.json, do it here.
	// I haven't managed to figure it out how to do resolvers in a cascade. This will do for now.

	const encryptedUser = select(STORE_KEY).getEncryptedUser()
	// If we don't have the encrypted user data, the request will be rejected either way. So, best not to waste any time on it.
	if (!encryptedUser) {
		dispatch(STORE_KEY).setComposerJson({})
	}

	// noinspection JSUnresolvedVariable,JSHint
	const {
		ltrecordsCompositionsUrl,
		ltrecordsApiKey,
		ltrecordsApiPwd
	} = _pixelgradeltRetailerEditCompositionData

	const composerJson = yield apiFetch({
		url: ltrecordsCompositionsUrl,
		method: 'POST',
		headers: {
			'Authorization': 'Basic ' + btoa(ltrecordsApiKey + ':' + ltrecordsApiPwd)
		},
		data: {
			user: encryptedUser,
			require: parts,
			composer: {
				'name': 'pixelgradelt/' + select(STORE_KEY).getHashId().toLowerCase(),
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

function setEncryptedUser (encryptedUser) {
	return {
		type: 'SET_ENCRYPTED_USER',
		encryptedUser: encryptedUser,
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
	reducer (state = DEFAULT_STATE, action) {
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

			case 'SET_ENCRYPTED_USER' :
				return {
					...state,
					encryptedUser: action.encryptedUser,
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
		setEncryptedUser,
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
		getEncryptedUser (state) {
			return state.encryptedUser || ''
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