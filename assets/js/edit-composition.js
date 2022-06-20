import { data, element, html } from './utils/index.js'
import CompositionState from './components/composition-state.js'
import './data/composition.js'

const {useDispatch, useSelect} = data
const {Fragment, render} = element

// noinspection JSUnresolvedVariable,JSHint
const {
	editedPostId,
	editedHashId,
	encryptedPDDetails,
	solutionIds,
	solutionContexts
} = _pressodyRetailerEditCompositionData

function App (props) {
	const {
		postId,
		hashId,
		encryptedPDDetails,
		solutionIds,
		solutionContexts
	} = props

	const {
		setPostId,
		setHashId,
		setEncryptedPDDetails,
		setSolutionIds,
		setSolutionContexts
	} = useDispatch('pressody_retailer/composition')

	setPostId(postId)
	setHashId(hashId)
	setEncryptedPDDetails(encryptedPDDetails)
	setSolutionIds(solutionIds)
	setSolutionContexts(solutionContexts)

	const solutions = useSelect((select) => {
		return select('pressody_retailer/composition').getSolutions()
	})

	const parts = useSelect((select) => {
		return select('pressody_retailer/composition').getParts()
	})

	const composerJson = useSelect((select) => {
		return select('pressody_retailer/composition').getComposerJson()
	})

	return html`
	  <${Fragment}>
		  <${CompositionState}
				  key="composition-state"
				  solutions=${solutions}
				  parts=${parts}
				  composerJson=${composerJson}
				  postId=${postId}
				  hashId=${hashId}
				  encryptedPDDetails=${encryptedPDDetails}
		  />
	  </${Fragment}>
	`
}

render(
	html`
	  <${App} postId=${editedPostId}
	          hashId=${editedHashId}
	          encryptedPDDetails=${encryptedPDDetails}
	          solutionIds=${solutionIds}
	          solutionContexts=${solutionContexts}/>`,
	document.getElementById('pressody_retailer-composition-state')
)
