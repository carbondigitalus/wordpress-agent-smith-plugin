# Agent Smith Plugin

## Introduction

The **Agent Smith Plugin** ensures uninterrupted WordPress core, plugin, and theme updates by providing a failover mechanism. If your WordPress site is unable to connect to the official WordPress.org update server, Agent Smith steps in to fetch updates from a specified **GitHub repository**, ensuring your site remains up-to-date even during connectivity issues.

## Installation

1. **Download the Plugin**:  
   Download the latest version of the Agent Smith plugin from the [Releases page](https://github.com/blogvault/wordpress-agent-smith-plugin/releases).

2. **Install via WordPress Dashboard**:
    - Go to your WordPress admin dashboard.
    - Navigate to `Plugins` > `Add New`.
    - Click `Upload Plugin` and select the `.zip` file you downloaded from the releases.
    - Click `Install Now` and activate the plugin once the installation is complete.

## How It Works

Once the plugin is activated, it will check WordPress.org for updates. If the connection fails, it will:

1. Use the GitHub repository URL provided in **Settings > General** to fetch updates.
2. Retrieve update data from JSON files stored in the GitHub repository.
3. Provide updates for **WordPress core, plugins, and themes** from the GitHub source.

## Setting Up Your GitHub Repository

Agent Smith requires a GitHub repository containing update data in JSON format. You will need to enter the **raw GitHub URL** of your repository in **Settings > General**.

### **Example Raw GitHub URL**

```
https://raw.githubusercontent.com/your-org/updates-repo/main
```

### **Required JSON Files**

Your repository must contain the following files:

#### `core.json` (For WordPress Core Updates)

```json
{
    "offers": [
        {
            "version": "6.5",
            "download": "https://github.com/your-org/wordpress-core/releases/download/v6.5/wordpress.zip"
        }
    ]
}
```

#### `plugins.json` (For Plugin Updates)

```json
{
    "your-plugin-slug": {
        "new_version": "1.1.0",
        "package": "https://raw.githubusercontent.com/your-org/updates-repo/main/plugins/your-plugin.zip",
        "slug": "your-plugin-slug",
        "url": "https://github.com/your-org/plugin-repo"
    }
}
```

#### `themes.json` (For Theme Updates)

```json
{
    "your-theme-slug": {
        "new_version": "2.0.0",
        "package": "https://github.com/your-org/theme-repo/releases/download/v2.0.0/theme.zip",
        "slug": "your-theme-slug",
        "url": "https://github.com/your-org/theme-repo"
    }
}
```

## Storing Plugin Files in a `plugins` Folder

Instead of hosting plugins in GitHub Releases, you can store plugin `.zip` files directly in a `plugins` folder inside your repository. The `plugins.json` file should reference the plugin `.zip` file using the raw GitHub URL format.

### **Example GitHub Repository Structure**

```
updates-repo/
│-- core.json
│-- plugins.json
│-- themes.json
│-- plugins/
│   │-- your-plugin.zip
│   │-- another-plugin.zip
```

### **Example `plugins.json` File Using a Plugins Folder**

```json
{
    "your-plugin-slug": {
        "new_version": "1.1.0",
        "package": "https://raw.githubusercontent.com/your-org/updates-repo/main/plugins/your-plugin.zip",
        "slug": "your-plugin-slug",
        "url": "https://github.com/your-org/plugin-repo"
    }
}
```

## Features

-   **GitHub-based Updates**: Fetch WordPress core, plugin, and theme updates from GitHub instead of WordPress.org.
-   **Customizable URL**: Easily set the update source via **Settings > General**.
-   **Seamless Failover**: If WordPress.org is down, Agent Smith retrieves updates from GitHub.

## GitHub Releases

We use GitHub's release system to distribute new versions of the Agent Smith Plugin. To download the latest version, visit the [Releases page](https://github.com/blogvault/wordpress-agent-smith-plugin/releases).

-   **Versioning**: Follows semantic versioning.
-   **Changelog**: Each release will include a changelog for easy tracking of changes and updates.

## Learn More

For more information about Agent Smith, visit the main [Agent Smith repository](https://github.com/blogvault/wordpress-agent-smith-plugin).

## License

Agent Smith Plugin is licensed under the [MIT License](LICENSE).
