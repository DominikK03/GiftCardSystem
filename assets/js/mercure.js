// Mercure EventSource subscriber
// Reads hub URL from <body data-mercure-hub="...">
// Subscribes to topics from <body data-mercure-topics="..."> (comma-separated)
// Default topic: /dashboard
// Dispatches CustomEvent('mercure:update', { detail: parsedData }) on document

const hubUrl = document.body.dataset.mercureHub;
if (hubUrl) {
    const topics = (document.body.dataset.mercureTopics || '/dashboard').split(',');
    const url = new URL(hubUrl);
    topics.forEach(topic => url.searchParams.append('topic', topic.trim()));

    const es = new EventSource(url);
    es.onmessage = (e) => {
        const data = JSON.parse(e.data);
        document.dispatchEvent(new CustomEvent('mercure:update', { detail: data }));
    };
}
