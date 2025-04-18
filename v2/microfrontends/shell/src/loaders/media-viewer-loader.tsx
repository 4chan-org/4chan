import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'

// This would be a real implementation that loads the Web Component
const loadMediaViewerComponent = async () => {
  // In a real implementation, this would dynamically import the Web Component
  // import('@microfrontends/media-viewer')
  
  console.log('MediaViewer component loaded')
  return true
}

const MediaViewerLoader = () => {
  const [loaded, setLoaded] = useState(false)
  const { id } = useParams<{ id: string }>()
  
  useEffect(() => {
    loadMediaViewerComponent().then(() => {
      setLoaded(true)
    })
  }, [])
  
  if (!loaded) {
    return <div>Loading Media Viewer...</div>
  }
  
  // In a real implementation, this would render the actual Web Component
  return (
    <div className="media-viewer-container">
      <h2>Media Viewer</h2>
      <div className="media-content">
        <p>Media with ID {id} would appear here</p>
        {/* This would be the actual Web Component: <media-viewer id={id}></media-viewer> */}
      </div>
    </div>
  )
}

export default MediaViewerLoader