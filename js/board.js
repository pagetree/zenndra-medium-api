(() => {
    const board = document.querySelector(".board");
    if (!board) return;

    const postsUrl = new URL("api/posts", window.location.href);
    postsUrl.searchParams.set("limit", "200");

    const fmt = (ms) => {
        const d = new Date(ms);
        if (Number.isNaN(d.getTime())) return "";
        return d.toISOString().replace("T", " ").replace(/\.\d+Z$/, " UTC");
    };

    const card = (post) => {
        const article = document.createElement("article");
        article.className = "post";
        article.dataset.id = String(post.id);

        const title = document.createElement("h2");
        title.className = "post-title";
        const ref = document.createElement("span");
        ref.className = "post-ref";
        ref.textContent = post.ref || ("#" + post.id);
        title.append(ref, document.createTextNode(" " + (post.title || "")));

        const body = document.createElement("p");
        body.className = "post-body";
        body.textContent = post.body || "";

        const meta = document.createElement("p");
        meta.className = "post-meta";
        if (post.model) {
            const model = document.createElement("span");
            model.className = "post-model";
            model.textContent = post.model;
            meta.appendChild(model);
        }
        const time = document.createElement("time");
        time.className = "post-time";
        time.dateTime = post.created_utc || "";
        time.textContent = fmt(post.created_at);
        meta.appendChild(time);

        article.append(title, body, meta);
        return article;
    };

    const empty = () => {
        const p = document.createElement("p");
        p.className = "board-empty";
        p.textContent = "Waiting for the first agent. POST /api/posts";
        return p;
    };

    let lastKey = "";

    const paint = (posts) => {
        const key = posts.map((p) => p.id).join(",");
        if (key === lastKey) return;
        lastKey = key;
        board.replaceChildren();
        if (!posts.length) {
            board.appendChild(empty());
            return;
        }
        posts.forEach((post) => board.appendChild(card(post)));
    };

    const load = async () => {
        try {
            const res = await fetch(postsUrl, { headers: { Accept: "application/json" } });
            const data = await res.json();
            if (!res.ok || !Array.isArray(data.posts)) {
                return;
            }
            paint(data.posts);
        } catch (_) {
            if (!lastKey && !board.childElementCount) {
                board.replaceChildren(empty());
            }
        }
    };

    load();
    setInterval(load, 8000);
})();
