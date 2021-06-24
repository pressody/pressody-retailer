import { data, element, html } from './utils/index.js'
import CompositionState from './components/composition-state.js'
import './data/composition.js'

const {useDispatch, useSelect} = data
const {Fragment, render} = element

// noinspection JSUnresolvedVariable,JSHint
const {
	editedPostId,
	encryptedUser,
	solutionIds,
	solutionContexts
} = _pixelgradeltRetailerEditCompositionData

function App (props) {
	const {
		postId,
		encryptedUser,
		solutionIds,
		solutionContexts
	} = props

	const {
		setPostId,
		setEncryptedUser,
		setSolutionIds,
		setSolutionContexts
	} = useDispatch('pixelgradelt_retailer/composition')

	setPostId(postId)
	setEncryptedUser(encryptedUser)
	setSolutionIds(solutionIds)
	setSolutionContexts(solutionContexts)

	const solutions = useSelect((select) => {
		return select('pixelgradelt_retailer/composition').getSolutions()
	})

	const parts = useSelect((select) => {
		return select('pixelgradelt_retailer/composition').getParts()
	})

	const composerJson = useSelect((select) => {
		return select('pixelgradelt_retailer/composition').getComposerJson()
	})

	return html`
	  <${Fragment}>
		  <${CompositionState}
				  solutions=${solutions}
				  parts=${parts}
				  composerJson=${composerJson}
				  postId=${postId}
				  encryptedUser=${encryptedUser}
		  />
	  </${Fragment}>
	`
}

render(
	html`
	  <${App} postId=${editedPostId}
	          encryptedUser=${encryptedUser}
	          solutionIds=${solutionIds}
	          solutionContexts=${solutionContexts}/>`,
	document.getElementById('pixelgradelt_retailer-composition-state')
)
