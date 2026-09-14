import { Component } from 'react';

/**
 * Without this, a render error anywhere unmounts the whole app and leaves a
 * blank page with nothing but a console message.
 */
export default class ErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { error: null };
  }

  static getDerivedStateFromError(error) {
    return { error };
  }

  componentDidCatch(error, info) {
    console.error('Caught by ErrorBoundary:', error, info);
  }

  render() {
    if (!this.state.error) return this.props.children;

    return (
      <div className="page container">
        <div className="auth-shell" style={{ textAlign: 'center' }}>
          <span className="empty-state__icon">🥀</span>
          <h1 className="page-title">Something went wrong</h1>
          <p className="page-subtitle" style={{ margin: '0 auto 24px' }}>
            This part of the page failed to load. The rest of GrowGuide is still fine.
          </p>
          <div style={{ display: 'flex', gap: 10, justifyContent: 'center' }}>
            <button className="btn" onClick={() => this.setState({ error: null })}>
              Try again
            </button>
            <a className="btn btn--outline" href="/">
              Back to the swap shelf
            </a>
          </div>
          {import.meta.env.DEV && (
            <pre
              style={{
                textAlign: 'left',
                marginTop: 24,
                fontSize: 12,
                color: 'var(--red-600)',
                whiteSpace: 'pre-wrap',
                background: 'var(--red-100)',
                padding: 14,
                borderRadius: 10,
              }}
            >
              {String(this.state.error?.stack || this.state.error)}
            </pre>
          )}
        </div>
      </div>
    );
  }
}
