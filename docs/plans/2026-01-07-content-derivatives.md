I would like to completely change the way how to create a content piece and create derivatives from it.

## Situation

The user has the challenge that for one content idea he has to create multiple derivatives, e.g. a blog post, a reddit post, a linkedIn post. 

## Complication

All derivatives have different wordings. It takes time to create those derivatives. It is difficult to have an overview which derivative was published or not.

## Solution

The goal is the following:

- For each content piece the user can add multiple background information text, e.g. multiple posts.
- The user can add more texts manually. A text is just plaintext, e.g. an article pasted into a text areay. And another article pasted into another textarea. 
- From each content piece and their background text the user can create multiple derivatives, e.g.
  - a blog post
  - a LinkedIn post
  - a Reddit post
  - a YouTube video script
  - ...
- Each derivative is created via AI by a pre-defined prompt.
- Each prompt is attached to a "Channel", e.g. LinkedIn, BlogPost, YouTube, Reddit, ...
- Each derivative can be edited and saved by the user separately.
- Each derivate has the following fields
  - Title
  - Text
  - Channel (e.g. LinkedIn, BlogPost, YouTube, Reddit, ...)
  - Status (e.g. Not started, Draft, Final, Published, Not planned)
  - isPublished (boolean)
  - Date of planned publication
- The user can quickly see which channels are already planned and published. This could be a table with the content pieces in rows and the channels in columns. In the cells we show the current status. Let's use colors to indicate the status, e.g. green for published, yellow for draft, red for not started, gray for not planned.
- For each content piece the user should be able to activate the desired channels, select a prompt for this channel and then in the background we create the derivatives based on the pre-defined prompts.
- It should be easy for the user to edit the different derivatives.

Please create an implementation plan. You can start from scratch here for the new content piece page.

Think about the necessary changes to our datamodel

Also think about a good UI which allows the user to easily

- add / manage the background text used by the AI
- Select the desired channels
- Edit the derivatives per channel
- View the current publishing status

Feel free to not update the existing "Content piece" page but create a new one. Also add it to the navigation.

