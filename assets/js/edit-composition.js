import { data, element, html } from './utils/index.js'
import CompositionState from './components/composition-state.js'
import './data/composition.js'

const {useDispatch, useSelect} = data
const {Fragment, render} = element

// noinspection JSUnresolvedVariable,JSHint
const {
	editedPostId,
	editedHashId,
	encryptedLTDetails,
	solutionIds,
	solutionContexts
} = _pixelgradeltRetailerEditCompositionData

function App (props) {
	const {
		postId,
		hashId,
		encryptedLTDetails,
		solutionIds,
		solutionContexts
	} = props

	const {
		setPostId,
		setHashId,
		setEncryptedLTDetails,
		setSolutionIds,
		setSolutionContexts
	} = useDispatch('pixelgradelt_retailer/composition')

	setPostId(postId)
	setHashId(hashId)
	setEncryptedLTDetails(encryptedLTDetails)
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
				  key="composition-state"
				  solutions=${solutions}
				  parts=${parts}
				  composerJson=${composerJson}
				  postId=${postId}
				  hashId=${hashId}
				  encryptedLTDetails=${encryptedLTDetails}
		  />
	  </${Fragment}>
	`
}

render(
	html`
	  <${App} postId=${editedPostId}
	          hashId=${editedHashId}
	          encryptedLTDetails=${encryptedLTDetails}
	          solutionIds=${solutionIds}
	          solutionContexts=${solutionContexts}/>`,
	document.getElementById('pixelgradelt_retailer-composition-state')
)
