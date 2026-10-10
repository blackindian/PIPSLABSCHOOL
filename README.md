# PIPSLAB Trading School website (Phase 1)
Static site: no build step.

## Deploy on GitHub Pages
1. Create a new GitHub repo and upload everything in this folder.
2. Settings > Pages > Deploy from a branch > main, folder / (root) > Save.
3. Your site goes live at https://YOUR-USERNAME.github.io/REPO-NAME/

## Edit content
Everything editable lives in `assets/data.js`: WhatsApp number (country code, no +), mentor posts, weekly events, lessons and quiz questions.
Optional: create a free Formspree form and paste its URL into `formspree` to receive sign-ups by email. If it is empty, the Join form opens WhatsApp.

## Phase 2 (needs a small backend)
Live market data and the AI assessment must call APIs with secret keys, so add a serverless function (Cloudflare Workers or Netlify) rather than putting keys in these files.
